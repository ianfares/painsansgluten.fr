<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Sequencing\SequenceGenerator;
use App\Settings\BillingSettings;
use App\Settings\ShopSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Génère la facture au passage de la commande à `paid` (PLAN.md §12).
 * **Immuable** une fois émise : le snapshot JSON permet de régénérer le
 * PDF à l'identique, sans jamais relire les données vivantes du produit
 * ou du client (CLAUDE.md §4).
 */
class IssueInvoiceAction
{
    public function __construct(private readonly SequenceGenerator $sequenceGenerator) {}

    public function execute(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $number = $this->sequenceGenerator->next(InvoiceType::Invoice->value, InvoiceType::Invoice->numberPrefix());
            $snapshot = $this->buildSnapshot($order, $number);

            $invoice = Invoice::query()->create([
                'order_id' => $order->id,
                'type' => InvoiceType::Invoice,
                'number' => $number,
                'issued_at' => now(),
                'snapshot' => $snapshot,
                'total_ht' => $order->total_ht,
                'total_vat' => $order->total_vat,
                'total_ttc' => $order->total_ttc,
            ]);

            $invoice->update(['pdf_path' => $this->generatePdf($invoice)]);

            return $invoice;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(Order $order, string $number): array
    {
        $billing = app(BillingSettings::class);
        $shop = app(ShopSettings::class);

        // Lignes produits + frais de port, regroupés par taux de TVA (le port a son propre taux).
        $shippingHt = (int) round($order->shipping_ttc / (1 + (float) $order->shipping_vat_rate / 100));
        // La pâtisserie offerte (0 €) n'entre pas dans le récapitulatif de TVA.
        $vatLines = $order->items->reject(fn ($item) => $item->is_free_gift)
            ->map(fn ($item) => ['rate' => (float) $item->vat_rate, 'ht' => $item->line_total_ht, 'ttc' => $item->line_total_ttc]);
        if ($order->shipping_ttc > 0) {
            $vatLines->push(['rate' => (float) $order->shipping_vat_rate, 'ht' => $shippingHt, 'ttc' => $order->shipping_ttc]);
        }
        $linesByVatRate = $vatLines->groupBy(fn (array $line) => (string) $line['rate'])
            ->map(fn ($lines, $rate) => [
                'rate' => (float) $rate,
                'base_ht' => $lines->sum('ht'),
                'vat' => $lines->sum(fn (array $line) => $line['ttc'] - $line['ht']),
            ])
            ->values()
            ->all();

        return [
            'number' => $number,
            'seller' => [
                'name' => $billing->company_name ?? $shop->shop_name,
                'legal_form' => $billing->legal_form,
                'share_capital' => $billing->share_capital,
                'address' => $billing->address,
                'siret' => $billing->siret,
                'rcs' => $billing->rcs,
                'vat_number' => $billing->vat_number,
                'footer_mentions' => $billing->invoice_footer_mentions,
            ],
            'customer' => [
                'name' => trim("{$order->billing_first_name} {$order->billing_last_name}"),
                'company' => $order->billing_company,
                'address' => trim("{$order->billing_line1} {$order->billing_line2}"),
                'postal_code' => $order->billing_postal_code,
                'city' => $order->billing_city,
                'country' => $order->billing_country,
                'email' => $order->email,
            ],
            'delivery' => self::deliveryFor($order),
            'lines' => $order->items->map(fn ($item) => [
                'designation' => $item->product_name,
                'reference' => $item->product_reference,
                'quantity' => $item->quantity,
                'unit_price_ht' => (int) round($item->unit_price_ttc / (1 + (float) $item->vat_rate / 100)),
                'discount_percent' => $item->unit_discount_ttc > 0 ? $order->discount_percent : 0,
                'vat_rate' => (float) $item->vat_rate,
                'total_ht' => $item->line_total_ht,
                'free_gift' => $item->is_free_gift,
            ])->all(),
            // Remise client (T27-L6) : produits seulement, totaux déjà après remise.
            'discount' => [
                'percent' => $order->discount_percent,
                'total_ttc' => $order->discount_total_ttc,
            ],
            'shipping' => [
                'total_ttc' => $order->shipping_ttc,
                'vat_rate' => (float) $order->shipping_vat_rate,
            ],
            'vat_summary' => $linesByVatRate,
            'total_ht' => $order->total_ht,
            'total_vat' => $order->total_vat,
            'total_ttc' => $order->total_ttc,
            'payment_method' => $order->payment_method->label(),
            'settlement_reference' => $order->settlement_reference,
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }

    /**
     * Adresse de livraison figée sur la facture (15/10/2026) : le mode, le
     * destinataire et le point (relais, commerçant ou labo) où le colis est remis.
     * Aussi utilisé pour l'affichage des factures émises avant cet ajout.
     *
     * @return array{method: string, recipient: string, point: ?string, address: ?string, postal_code: ?string, city: ?string}
     */
    public static function deliveryFor(Order $order): array
    {
        $recipient = trim("{$order->shipping_first_name} {$order->shipping_last_name}")
            ?: trim("{$order->billing_first_name} {$order->billing_last_name}");
        $point = (array) $order->relay_snapshot;

        return [
            'method' => $order->delivery_method->label(),
            'recipient' => $recipient,
            'point' => $order->relay_name ?: null,
            'address' => isset($point['address_line1']) ? (string) $point['address_line1'] : null,
            'postal_code' => isset($point['postal_code']) ? (string) $point['postal_code'] : null,
            'city' => isset($point['city']) ? (string) $point['city'] : null,
        ];
    }

    private function generatePdf(Invoice $invoice): string
    {
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
        $path = "invoices/{$invoice->number}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
