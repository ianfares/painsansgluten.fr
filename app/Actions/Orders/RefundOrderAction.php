<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\RefundNotAvailable;
use App\Models\Order;
use App\Services\Orders\OrderStateMachine;

/**
 * Action BO « Rembourser » (PLAN.md §14, T17). **Virement uniquement pour
 * l'instant** : le remboursement d'un paiement carte doit appeler l'API
 * Stripe (PLAN §9), qui n'existe pas encore (T14 non livrée) — plutôt que
 * d'inventer un faux appel, cette action refuse explicitement ce cas
 * (voir docs/DECISIONS.md, T17). La commande n'est jamais modifiée.
 */
class RefundOrderAction
{
    public function __construct(private readonly OrderStateMachine $orderStateMachine) {}

    /**
     * @throws RefundNotAvailable
     */
    public function execute(Order $order, int $adminId): Order
    {
        if ($order->payment_method === PaymentMethod::Stripe) {
            throw RefundNotAvailable::stripeNotIntegratedYet();
        }

        return $this->orderStateMachine->transition(
            $order,
            OrderStatus::Refunded,
            actorType: 'admin',
            actorId: $adminId,
            comment: 'Remboursement du virement effectué manuellement par l\'admin.',
        );
    }
}
