<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoiceAction;
use App\Enums\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Settings\BillingSettings;
use App\Settings\HomepageSettings;
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

test('facture : facturation à gauche, livraison à droite, pied de page = mentions saisies dans le BO uniquement', function () {
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
        ->and(substr($html, $footerPos))->toContain('Pénalités de retard : taux légal.')
        // Plus de ligne vendeur ajoutée automatiquement (doublon avec les mentions du BO), ni « 10000 » brut.
        ->and($html)->not->toContain('Aguyapi')
        ->and($html)->not->toContain('10000')
        ->and($html)->not->toContain('au capital de');
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

test('facture : le logo de la boutique est intégré en haut du document', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    $homepage = app(HomepageSettings::class);
    $homepage->logo_path = 'branding/logo.png';
    $homepage->save();

    $html = renderInvoice(Order::factory()->create());

    expect($html)->toContain('<img src="data:image/png;base64,')
        ->and(strpos($html, 'data:image/png'))->toBeLessThan(strpos($html, '<h1>'));
});

test('facture : sans logo renseigné, pas d\'image', function () {
    $homepage = app(HomepageSettings::class);
    $homepage->logo_path = null;
    $homepage->save();

    expect(renderInvoice(Order::factory()->create()))->not->toContain('<img');
});
