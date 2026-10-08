<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Mail\StripePaymentAnomalyMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Services\Orders\OrderStateMachine;
use App\Services\Shipping\ShippingDateCalculator;
use App\Settings\ShopSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Event;

/**
 * Traitement d'un événement Stripe déjà authentifié (signature vérifiée par
 * le contrôleur). Seule source de vérité du paiement (CLAUDE.md §4, PLAN.md §10).
 *
 * Idempotence : l'`event.id` est enregistré dans la même transaction que son
 * effet ; un doublon (UNIQUE) est ignoré, une erreur annule tout et Stripe
 * renverra l'événement.
 */
class HandleStripeWebhookAction
{
    public function __construct(
        private readonly OrderStateMachine $orderStateMachine,
        private readonly ShippingDateCalculator $shippingDateCalculator,
    ) {}

    public function execute(Event $event): void
    {
        $anomaly = null;

        try {
            DB::transaction(function () use ($event, &$anomaly) {
                StripeEvent::query()->create(['event_id' => $event->id, 'type' => $event->type, 'processed_at' => now()]);

                $object = $event->data->object;

                $anomaly = match (true) {
                    ! $object instanceof Session && ! $object instanceof Charge => null,
                    $object instanceof Session && in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) => $this->sessionPaid($object),
                    $object instanceof Session && $event->type === 'checkout.session.async_payment_failed' => $this->sessionFailed($object),
                    $object instanceof Session && $event->type === 'checkout.session.expired' => $this->sessionExpired($object),
                    $object instanceof Charge && $event->type === 'charge.refunded' => $this->chargeRefunded($object),
                    default => null,
                };
            });
        } catch (UniqueConstraintViolationException) {
            Log::info('Stripe : événement déjà traité, ignoré.', ['event_id' => $event->id]);

            return;
        }

        if ($anomaly instanceof Order) {
            $to = app(ShopSettings::class)->admin_notification_email;
            if ($to) {
                Mail::to($to)->queue(new StripePaymentAnomalyMail($anomaly));
            }
        }
    }

    /**
     * @return Order|null la commande en anomalie de montant, sinon null
     */
    private function sessionPaid(Session $session): ?Order
    {
        if ($session->payment_status !== 'paid') {
            return null; // moyen asynchrone : on attend async_payment_succeeded
        }

        $order = $this->lockOrder($session);
        if (! $order) {
            return null;
        }

        if (! $order->status->canTransitionTo(OrderStatus::Paid)) {
            Log::info('Stripe : paiement reçu pour une commande déjà traitée, ignoré.', ['order' => $order->number, 'status' => $order->status->value]);

            return null;
        }

        if ((int) $session->amount_total !== $order->total_ttc || $session->currency !== 'eur') {
            $order->payment_anomaly = true;
            $order->save();
            Log::error('Stripe : montant payé différent du total de la commande, statut inchangé.', [
                'order' => $order->number,
                'expected' => $order->total_ttc,
                'received' => $session->amount_total,
                'currency' => $session->currency,
            ]);

            return $order;
        }

        Payment::query()
            ->where('order_id', $order->id)
            ->where('provider_ref', $session->id)
            ->update([
                'status' => 'paid',
                'provider_ref' => (string) $session->payment_intent,
                'raw' => json_encode(['checkout_session' => $session->id, 'payment_intent' => $session->payment_intent]),
            ]);

        // Le paiement est réel : il doit être enregistré même si les réglages
        // d'expédition sont incomplets (date à fixer à la main dans ce cas).
        try {
            $order->planned_ship_date = Carbon::instance($this->shippingDateCalculator->forInstant(CarbonImmutable::now()));
            $order->save();
        } catch (ShippingNotConfigured) {
            Log::error('Stripe : commande payée sans date d\'expédition (paramètres d\'expédition incomplets).', ['order' => $order->number]);
        }

        $this->orderStateMachine->transition($order, OrderStatus::Paid, actorType: 'stripe', comment: 'Paiement carte confirmé par Stripe.');

        return null;
    }

    private function sessionFailed(Session $session): null
    {
        $order = $this->lockOrder($session);
        if ($order && $order->status === OrderStatus::PendingPayment) {
            $this->markPayment($session->id, 'failed');
            $this->orderStateMachine->transition($order, OrderStatus::PaymentFailed, actorType: 'stripe', comment: 'Paiement refusé par Stripe.');
        }

        return null;
    }

    private function sessionExpired(Session $session): null
    {
        $order = $this->lockOrder($session);
        if (! $order) {
            return null;
        }

        $this->markPayment($session->id, 'expired');

        // Le client a pu relancer un paiement depuis : seule l'expiration de la
        // dernière session ouverte annule la commande.
        $hasOtherPending = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', 'pending')
            ->exists();

        if ($order->status === OrderStatus::PendingPayment && ! $hasOtherPending) {
            $this->orderStateMachine->transition($order, OrderStatus::Cancelled, actorType: 'stripe', comment: 'Session de paiement Stripe expirée.');
        }

        return null;
    }

    /**
     * Remboursement fait depuis le tableau de bord Stripe (si fait depuis le
     * BO, la commande est déjà `refunded` et l'événement est sans effet).
     */
    private function chargeRefunded(Charge $charge): null
    {
        if ((int) $charge->amount_refunded < (int) $charge->amount) {
            Log::warning('Stripe : remboursement partiel ignoré (V1 : total uniquement).', ['payment_intent' => $charge->payment_intent]);

            return null;
        }

        $payment = Payment::query()
            ->where('method', PaymentMethod::Stripe)
            ->where('provider_ref', (string) $charge->payment_intent)
            ->first();

        $order = $payment ? Order::query()->whereKey($payment->order_id)->lockForUpdate()->first() : null;

        if ($order && $order->status->canTransitionTo(OrderStatus::Refunded)) {
            $payment->update(['status' => 'refunded']);
            $this->orderStateMachine->transition($order, OrderStatus::Refunded, actorType: 'stripe', comment: 'Remboursement effectué depuis le tableau de bord Stripe.');
        }

        return null;
    }

    private function lockOrder(Session $session): ?Order
    {
        $orderId = $session->metadata->order_id ?? $session->client_reference_id;

        $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
        if (! $order) {
            Log::warning('Stripe : commande introuvable pour la session.', ['session' => $session->id]);
        }

        return $order;
    }

    private function markPayment(string $sessionId, string $status): void
    {
        Payment::query()->where('provider_ref', $sessionId)->update(['status' => $status]);
    }
}
