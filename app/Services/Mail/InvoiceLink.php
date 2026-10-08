<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Invoice;
use Illuminate\Support\Facades\URL;

/**
 * Lien de téléchargement d'une facture/avoir envoyé par email : URL signée
 * 30 jours, utilisable aussi par un client sans compte (PLAN.md §12).
 */
class InvoiceLink
{
    public static function for(Invoice $invoice): string
    {
        return URL::temporarySignedRoute('invoices.download', now()->addDays(30), ['invoice' => $invoice]);
    }
}
