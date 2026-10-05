<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compte;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Téléchargement de facture/avoir (PLAN.md §12) : client propriétaire
 * (policy) ou invité via URL signée 30 jours — jamais d'accès libre
 * (le PDF est stocké hors du disque public).
 */
class InvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): Response
    {
        if (! $request->hasValidSignature()) {
            Gate::authorize('view', $invoice);
        }

        abort_unless($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return response(Storage::disk('local')->get($invoice->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"',
        ]);
    }
}
