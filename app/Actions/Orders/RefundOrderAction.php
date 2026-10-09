<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\RefundNotAvailable;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderStateMachine;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Action BO « Rembourser » (PLAN.md §10, §14) — montant total uniquement en V1.
 * Carte : remboursement réel via l'API Stripe (clé d'idempotence par commande,
 * un double clic ne rembourse jamais deux fois). Virement : remboursé à la
 * main par la boutique, l'action enregistre seulement le remboursement.
 */
class RefundOrderAction
{
    public function __construct(
        private readonly OrderStateMachine $orderStateMachine,
        private readonly StripeClient $stripe,
    ) {}

    /**
     * @throws RefundNotAvailable
     */
    public function execute(Order $order, int $adminId): Order
    {
        // État relu en base : une commande déjà remboursée (double clic, autre admin)
        // ou jamais payée ne déclenche aucun appel à Stripe.
        if (! $order->fresh()?->status->canTransitionTo(OrderStatus::Refunded)) {
            throw RefundNotAvailable::notRefundable();
        }

        if ($order->payment_method === PaymentMethod::BankTransfer) {
            return $this->orderStateMachine->transition(
                $order,
                OrderStatus::Refunded,
                actorType: 'admin',
                actorId: $adminId,
                comment: 'Remboursement du virement effectué manuellement par l\'admin.',
            );
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('method', PaymentMethod::Stripe)
            ->where('status', 'paid')
            ->first();

        if (! $payment || ! str_starts_with((string) $payment->provider_ref, 'pi_')) {
            throw RefundNotAvailable::stripePaymentNotFound();
        }

        try {
            $this->stripe->refunds->create(
                ['payment_intent' => $payment->provider_ref],
                ['idempotency_key' => "refund-order-{$order->id}"],
            );
        } catch (ApiErrorException $e) {
            throw RefundNotAvailable::stripeError($e->getMessage());
        }

        $payment->update(['status' => 'refunded']);

        return $this->orderStateMachine->transition(
            $order,
            OrderStatus::Refunded,
            actorType: 'admin',
            actorId: $adminId,
            comment: 'Remboursement carte effectué via Stripe.',
        );
    }
}
