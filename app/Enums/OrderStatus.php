<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statuts d'une commande (PLAN.md §9.2). Les transitions autorisées entre
 * statuts sont appliquées par `App\Services\Orders\OrderStateMachine` (T12),
 * jamais par affectation directe de `orders.status`.
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PaymentFailed = 'payment_failed';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'En attente de paiement',
            self::Paid => 'Paiement accepté',
            self::Preparing => 'En préparation',
            self::Shipped => 'Expédiée',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
            self::Refunded => 'Remboursée',
            self::PaymentFailed => 'Paiement échoué',
        };
    }

    /**
     * Statuts vers lesquels cette commande peut transiter (PLAN.md §9.2).
     *
     * @return list<self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::PaymentFailed, self::Cancelled],
            self::PaymentFailed => [self::Paid, self::Cancelled],
            self::Paid => [self::Preparing, self::Refunded],
            self::Preparing => [self::Shipped, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStatuses(), true);
    }
}
