<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * Une facture n'appartient qu'au client propriétaire de la commande
 * (QUALITE.md §2.4, IDOR). L'accès invité passe par une URL signée,
 * jamais par cette policy.
 */
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->order->user_id === $user->id;
    }
}
