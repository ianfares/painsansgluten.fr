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
    public static function stripeNotIntegratedYet(): self
    {
        return new self(
            'Remboursement carte bancaire indisponible pour le moment : l\'intégration Stripe (T14) '.
            'n\'est pas encore livrée. Remboursez directement depuis le tableau de bord Stripe en attendant, '.
            'puis contactez le développeur pour que cette commande soit marquée remboursée manuellement.'
        );
    }
}
