<?php

declare(strict_types=1);

namespace App\Exceptions\Orders;

use RuntimeException;

/**
 * Levée quand l'action BO « Valider le virement reçu » est tentée sur une
 * commande qui n'est pas un virement en attente (PLAN.md §11, T15).
 */
class BankTransferValidationNotAllowed extends RuntimeException
{
    public static function notBankTransfer(): self
    {
        return new self('Cette commande n\'est pas réglée par virement.');
    }

    public static function notPending(): self
    {
        return new self('Cette commande n\'est plus en attente de paiement (déjà payée ou annulée).');
    }
}
