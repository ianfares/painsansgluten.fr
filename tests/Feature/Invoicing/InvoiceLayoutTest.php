<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoiceAction;
use App\Enums\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Settings\BillingSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $billing = app(BillingSettings::class);
    $billing->company_name = 'Aguyapi';
    $billing->legal_form = 'Sasu';
    $billing->share_capital = '10000';
    $billing->address = '2 rue Jean Goujon, 78180 Montigny-le-Bretonneux';
    $billing->siret = '12345678901234';
    $billing->rcs = 'Versailles 123 456 789';
    $billing->vat_number = 'FR12123456789';
    $billing->invoice_footer_mentions = 'Pénalités de retard : taux légal.';
    $billing->save();
});

function renderInvoice(Order $order): string
{
    OrderItem::factory()->for($order)->create();
    $invoice = app(IssueInvoiceAction::class)->execute($order);

    return preg_replace('/\s+/u', ' ', view('pdf.invoice', ['invoice' => $invoice->fresh()])->render());
}

test('facture : facturation à gauche, livraison à droite, vendeur en pied de page (sans « 10000 » brut)', function () {
    $html = renderInvoice(Order::factory()->create([
        'billing_first_name' => 'Jeanne', 'billing_last_name' => 'Dupont',
        'billing_line1' => '1 rue du Pain', 'billing_postal_code' => '50300', 'billing_city' => 'Avranches',
        'delivery_method' => DeliveryMethod::MerchantPickup,
        'relay_name' => 'Épicerie du Port',
        'relay_snapshot' => ['name' => 'Épicerie du Port', 'address_line1' => '3 quai du Port', 'postal_code' => '50400', 'city' => 'Granville'],
    ]));

    $billingPos = strpos($html, 'Adresse de facturation');
    $deliveryPos = strpos($html, 'Adresse de livraison');
    $footerPos = strpos($html, '<div class="footer">');

    expect($billingPos)->toBeLessThan($deliveryPos)
        ->and($deliveryPos)->toBeLessThan($footerPos)
        ->and($html)->toContain('Jeanne Dupont')->toContain('50300 Avranches')
        ->toContain('Retrait chez un commerçant partenaire')->toContain('Épicerie du Port')->toContain('3 quai du Port')->toContain('50400 Granville')
        ->and(substr($html, $footerPos))->toContain('SASU Aguyapi au capital de 10 000 € — 2 rue Jean Goujon, 78180 Montigny-le-Bretonneux — SIRET : 12345678901234 — RCS : Versailles 123 456 789 — TVA intracommunautaire : FR12123456789')
        ->toContain('Pénalités de retard : taux légal.')
        // Le vendeur n'apparaît plus en haut, et « 10000 » n'est plus affiché brut.
        ->and(substr($html, 0, $footerPos))->not->toContain('Aguyapi')
        ->and($html)->not->toContain('10000');
});

test('facture : la livraison est figée dans la facture ; une facture plus ancienne la reprend de la commande', function () {
    $order = Order::factory()->create(['delivery_method' => DeliveryMethod::ChronopostRelay, 'relay_name' => 'Relais Avranches', 'relay_snapshot' => ['name' => 'Relais Avranches', 'postal_code' => '50300']]);
    OrderItem::factory()->for($order)->create();
    $invoice = app(IssueInvoiceAction::class)->execute($order);

    expect($invoice->snapshot['delivery']['method'])->toBe('Chronopost Relais')
        ->and($invoice->snapshot['delivery']['point'])->toBe('Relais Avranches');

    $snapshot = $invoice->snapshot;
    unset($snapshot['delivery']);
    $invoice->forceFill(['snapshot' => $snapshot])->saveQuietly();

    expect(view('pdf.invoice', ['invoice' => $invoice->fresh()])->render())->toContain('Relais Avranches');
});
