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
    // Commande validée par l'admin sans encaissement (T27-L10) : jamais proposé au client.
    case CreditSettlement = 'credit_settlement';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Carte bancaire (Stripe)',
            self::BankTransfer => 'Virement bancaire',
            self::CreditSettlement => 'Réglé par avoir',
        };
    }
}
