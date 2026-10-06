<?php

declare(strict_types=1);

use App\Enums\InvoiceType;
use App\Filament\Pages\ExportInvoicesCsv;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(Admin::factory()->create(), 'admin');
});

test('la liste des factures affiche une facture', function () {
    $invoice = Invoice::factory()->create();

    Livewire::test(ListInvoices::class)->assertCanSeeTableRecords([$invoice]);
});

test('le filtre type distingue factures et avoirs', function () {
    $invoice = Invoice::factory()->create(['type' => InvoiceType::Invoice]);
    $creditNote = Invoice::factory()->create(['type' => InvoiceType::CreditNote]);

    Livewire::test(ListInvoices::class)
        ->filterTable('type', InvoiceType::CreditNote->value)
        ->assertCanSeeTableRecords([$creditNote])
        ->assertCanNotSeeTableRecords([$invoice]);
});

test('l\'action télécharger est visible et pointe vers une URL signée valide', function () {
    $invoice = Invoice::factory()->create(['pdf_path' => 'invoices/test.pdf']);
    Storage::disk('local')->put('invoices/test.pdf', '%PDF-1.4 fake');

    Livewire::test(ListInvoices::class)->assertTableActionVisible('download', $invoice);

    // Le téléchargement BO réutilise le même contrôleur/mécanisme signé que
    // le lien invité (InvoiceDownloadController) — son autorisation est déjà
    // couverte par tests/Feature/Invoicing/InvoiceGenerationTest.php ; on
    // vérifie ici juste qu'une URL signée pour ce même contrôleur fonctionne.
    $signedUrl = URL::temporarySignedRoute('invoices.download', now()->addMinutes(10), ['invoice' => $invoice]);
    $this->get($signedUrl)->assertOk();
});

test('l\'export CSV contient les factures de la période avec le bon format', function () {
    $order = Order::factory()->create(['number' => 'C2026-00042', 'first_name' => 'Jeanne', 'last_name' => 'Dupont']);
    $invoice = Invoice::factory()->create([
        'order_id' => $order->id,
        'number' => 'F2026-00001',
        'issued_at' => '2026-10-05',
        'total_ht' => 1000,
        'total_vat' => 55,
        'total_ttc' => 1055,
        'snapshot' => [
            'customer' => ['name' => 'Jeanne Dupont'],
            'vat_summary' => [
                ['rate' => 5.5, 'base_ht' => 1000, 'vat' => 55],
            ],
        ],
    ]);

    // Hors période : ne doit pas apparaître dans l'export.
    Invoice::factory()->create(['issued_at' => '2020-01-01']);

    $response = Livewire::test(ExportInvoicesCsv::class)
        ->fillForm(['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->call('export')
        ->assertFileDownloaded();

    $content = base64_decode(data_get($response->effects, 'download.content'));

    expect($content)->toContain('F2026-00001')
        ->toContain('C2026-00042')
        ->toContain('Jeanne Dupont')
        ->toContain('10,00'); // total HT en euros (1000 centimes)
});
