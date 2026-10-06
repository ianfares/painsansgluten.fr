<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export comptable CSV des factures et avoirs sur une période (PLAN.md §14).
 * Format `À VALIDER` avec le comptable (colonnes proposées ici, PLAN §14) :
 * séparateur `;`, UTF-8 avec BOM (ouverture directe dans Excel).
 *
 * @property Form $form
 */
class ExportInvoicesCsv extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Export comptable';

    protected static string $view = 'filament.pages.export-invoices-csv';

    /** @var array<string, mixed> */
    public array $data = [];

    private const VAT_RATES = [2.1, 5.5, 10.0, 20.0];

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->label('Du')->required(),
                DatePicker::make('to')->label('Au')->required()->afterOrEqual('from'),
            ])
            ->statePath('data');
    }

    public function export(): StreamedResponse
    {
        $state = $this->form->getState();

        $validated = Validator::make($state, [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ])->validate();

        $invoices = Invoice::query()
            ->whereDate('issued_at', '>=', $validated['from'])
            ->whereDate('issued_at', '<=', $validated['to'])
            ->orderBy('issued_at')
            ->with('order')
            ->get();

        $filename = "export-comptable-{$validated['from']}-au-{$validated['to']}.csv";

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\u{FEFF}"); // BOM UTF-8 pour Excel.

            $header = ['Numéro', 'Date', 'Type', 'N° commande', 'Client', 'Moyen de paiement'];
            foreach (self::VAT_RATES as $rate) {
                $label = rtrim(rtrim(number_format($rate, 1, ',', ''), '0'), ',').'%';
                $header[] = "HT {$label}";
                $header[] = "TVA {$label}";
            }
            $header[] = 'Total HT';
            $header[] = 'Total TVA';
            $header[] = 'Total TTC';
            fputcsv($handle, $header, ';');

            foreach ($invoices as $invoice) {
                fputcsv($handle, $this->rowFor($invoice), ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return list<string>
     */
    private function rowFor(Invoice $invoice): array
    {
        $sign = $invoice->type === InvoiceType::CreditNote ? -1 : 1;
        $customer = $invoice->snapshot['customer']['name'] ?? trim("{$invoice->order->first_name} {$invoice->order->last_name}");
        $vatSummary = collect($invoice->snapshot['vat_summary'] ?? []);

        $row = [
            $invoice->number,
            $invoice->issued_at->format('d/m/Y'),
            $invoice->type->label(),
            $invoice->order->number,
            $customer,
            $invoice->order->payment_method->label(),
        ];

        foreach (self::VAT_RATES as $rate) {
            $line = $vatSummary->first(fn (array $l): bool => abs($l['rate'] - $rate) < 0.01);
            $row[] = $this->money(($line['base_ht'] ?? 0) * $sign);
            $row[] = $this->money(($line['vat'] ?? 0) * $sign);
        }

        $row[] = $this->money($invoice->total_ht);
        $row[] = $this->money($invoice->total_vat);
        $row[] = $this->money($invoice->total_ttc);

        return $row;
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }
}
