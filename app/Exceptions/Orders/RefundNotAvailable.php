<?php

declare(strict_types=1);

namespace App\Exceptions\Orders;

use RuntimeException;

/**
 * Levée quand un remboursement est tenté sur une commande payée par carte
 * (Stripe) avant que T14 (intégration Stripe) ne soit livrée — voir
 * docs/DECISIONS.md, T17 : jamais de faux appel API inventé (CLAUDE.md §3.1).
 */
class RefundNotAvailable extends RuntimeException
{
    public static function stripePaymentNotFound(): self
    {
        return new self('Paiement Stripe introuvable pour cette commande : remboursez depuis le tableau de bord Stripe, la commande sera mise à jour automatiquement.');
    }

    public static function stripeError(string $message): self
    {
        return new self("Stripe a refusé le remboursement : {$message}");
    }
}
