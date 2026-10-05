<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Sequencing\SequenceGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Génère l'avoir au remboursement d'une commande (PLAN.md §12) :
 * montants **négatifs**, toujours lié à la facture d'origine. V1 :
 * remboursement total uniquement (PLAN.md §9).
 */
class IssueCreditNoteAction
{
    public function __construct(private readonly SequenceGenerator $sequenceGenerator) {}

    public function execute(Order $order): Invoice
    {
        $originalInvoice = $order->invoices()->where('type', InvoiceType::Invoice)->latest('id')->first();

        if (! $originalInvoice) {
            throw new RuntimeException("Impossible d'émettre un avoir : aucune facture d'origine pour la commande {$order->number}.");
        }

        return DB::transaction(function () use ($order, $originalInvoice) {
            $number = $this->sequenceGenerator->next(InvoiceType::CreditNote->value, InvoiceType::CreditNote->numberPrefix());

            $snapshot = $originalInvoice->snapshot;
            $snapshot['number'] = $number;
            $snapshot['related_invoice_number'] = $originalInvoice->number;

            $creditNote = Invoice::query()->create([
                'order_id' => $order->id,
                'type' => InvoiceType::CreditNote,
                'number' => $number,
                'issued_at' => now(),
                'snapshot' => $snapshot,
                'total_ht' => -$originalInvoice->total_ht,
                'total_vat' => -$originalInvoice->total_vat,
                'total_ttc' => -$originalInvoice->total_ttc,
                'related_invoice_id' => $originalInvoice->id,
            ]);

            $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $creditNote]);
            $path = "invoices/{$creditNote->number}.pdf";
            Storage::disk('local')->put($path, $pdf->output());
            $creditNote->update(['pdf_path' => $path]);

            return $creditNote;
        });
    }
}
