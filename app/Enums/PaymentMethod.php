<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Moyen de paiement d'une commande (PLAN.md §1, §10, §11).
 */
enum PaymentMethod: string
{
    case Stripe = 'stripe';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Carte bancaire (Stripe)',
            self::BankTransfer => 'Virement bancaire',
        };
    }
}
