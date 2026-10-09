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
use App\Services\Mail\AdminMailer;
use App\Services\Orders\OrderStateMachine;
use App\Services\Shipping\ShippingDateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        /** @var array{order: Order, reason: string}|null $anomaly */
        $anomaly = DB::transaction(function () use ($event) {
            // Seul l'event_id peut être en doublon ici : toute autre erreur annule
            // la transaction et remonte (Stripe renverra l'événement).
            $inserted = StripeEvent::query()->insertOrIgnore([
                'event_id' => $event->id,
                'type' => $event->type,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                Log::info('Stripe : événement déjà traité, ignoré.', ['event_id' => $event->id]);

                return null;
            }

            $object = $event->data->object;

            return match (true) {
                $object instanceof Session && in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) => $this->sessionPaid($object),
                $object instanceof Session && $event->type === 'checkout.session.async_payment_failed' => $this->sessionFailed($object),
                $object instanceof Session && $event->type === 'checkout.session.expired' => $this->sessionExpired($object),
                $object instanceof Charge && $event->type === 'charge.refunded' => $this->chargeRefunded($object),
                default => null,
            };
        });

        if ($anomaly !== null) {
            AdminMailer::queue(new StripePaymentAnomalyMail($anomaly['order'], $anomaly['reason']));
        }
    }

    /**
     * @return array{order: Order, reason: string}|null anomalie à signaler à l'admin
     */
    private function sessionPaid(Session $session): ?array
    {
        if ($session->payment_status !== 'paid') {
            return null; // moyen asynchrone : on attend async_payment_succeeded
        }

        $order = $this->lockOrder($session);
        if (! $order) {
            return null;
        }

        if (! $order->status->canTransitionTo(OrderStatus::Paid)) {
            // Session déjà traitée (événement en double) : rien à faire. Sinon c'est
            // un second encaissement réel (ancienne session payée, commande annulée…) :
            // l'argent est chez Stripe, l'admin doit le rembourser.
            $payment = Payment::query()->where('order_id', $order->id)->where('provider_ref', $session->id)->first();
            if (! $payment || ! in_array($payment->status, ['pending', 'superseded', 'expired'], true)) {
                Log::info('Stripe : paiement déjà enregistré pour cette session, ignoré.', ['order' => $order->number]);

                return null;
            }

            $payment->update([
                'status' => 'paid_unexpected',
                'provider_ref' => (string) $session->payment_intent,
                'raw' => ['checkout_session' => $session->id, 'payment_intent' => $session->payment_intent],
            ]);
            $order->payment_anomaly = true;
            $order->save();
            Log::error('Stripe : paiement reçu sur une commande déjà payée ou annulée, à rembourser.', ['order' => $order->number, 'status' => $order->status->value]);

            return ['order' => $order, 'reason' => "Un paiement de {$this->euros((int) $session->amount_total)} a été encaissé alors que la commande était déjà « {$order->status->label()} » (paiement en double ou commande annulée). Remboursez-le depuis le tableau de bord Stripe."];
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

            return ['order' => $order, 'reason' => "Le montant payé ({$this->euros((int) $session->amount_total)}) ne correspond pas au total de la commande. La commande n'a pas été passée en « payée »."];
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

        $payment = Payment::query()->where('provider_ref', $session->id)->first();
        $wasOpen = $payment?->status === 'pending';
        $this->markPayment($session->id, 'expired');

        // Session remplacée par une relance de paiement (`superseded`), ou autre
        // session encore ouverte : la commande reste en attente.
        if (! $wasOpen) {
            return null;
        }

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

    private function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
