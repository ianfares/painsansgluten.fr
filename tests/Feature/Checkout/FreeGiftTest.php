<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoiceAction;
use App\Actions\Orders\CreateManualOrderAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\ManageFreeGiftSettings;
use App\Livewire\CartWidget;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\FreeGiftSettings;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

/**
 * Pâtisserie offerte (20/10/2026) : au-delà d'un seuil de produits (après
 * remise, hors port), ligne « offerte » à 0 € ajoutée à la commande ; son
 * poids compte dans le colis.
 */
beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->save();
    // Port : 6 € TTC jusqu'à 1 kg, 12 € TTC au-delà.
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 1000, 'price_ht' => 500]);
    ShippingRate::factory()->create(['min_weight_g' => 1001, 'max_weight_g' => 5000, 'price_ht' => 1000]);

    $gift = app(FreeGiftSettings::class);
    $gift->enabled = true;
    $gift->threshold_ttc = 3000;
    $gift->label = 'Pâtisserie offerte';
    $gift->weight_g = 200;
    $gift->save();
});

function giftCart(int $priceTtc, int $quantity = 1, int $weightG = 300): void
{
    app(CartService::class)->add(Product::factory()->create(['price_ttc' => $priceTtc, 'vat_rate' => 5.5, 'shipping_weight_g' => $weightG]), $quantity);
}

function giftOrder(): Order
{
    return app(CreateOrderAction::class)->execute(
        app(CartService::class)->currentCart(),
        ['email' => 'cadeau@example.com', 'first_name' => 'Jeanne', 'last_name' => 'Dupont', 'phone' => '0612345678'],
        ['first_name' => 'Jeanne', 'last_name' => 'Dupont', 'line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches'],
        ['relay_id' => 'REL-1', 'relay_name' => 'Relais', 'relay_snapshot' => ['name' => 'Relais']],
        PaymentMethod::Stripe,
    );
}

test('seuil atteint (borne incluse) : ligne offerte à 0 €, total inchangé', function () {
    giftCart(3000);

    $order = giftOrder();
    $gift = $order->items->firstWhere('is_free_gift', true);

    expect($order->items)->toHaveCount(2)
        ->and($gift->product_name)->toBe('Pâtisserie offerte')
        ->and($gift->product_id)->toBeNull()
        ->and($gift->quantity)->toBe(1)
        ->and($gift->unit_price_ttc)->toBe(0)
        ->and($gift->line_total_ttc)->toBe(0)
        ->and($gift->line_total_ht)->toBe(0)
        ->and($gift->weight_g)->toBe(200)
        ->and($order->subtotal_ttc)->toBe(3000)
        ->and($order->total_ttc)->toBe(3000 + 600);
});

test('sous le seuil : pas de ligne offerte', function () {
    giftCart(2999);

    $order = giftOrder();

    expect($order->items)->toHaveCount(1)
        ->and($order->items->contains('is_free_gift', true))->toBeFalse();
});

test('désactivée dans l\'admin : pas de ligne offerte même au-dessus du seuil', function () {
    $gift = app(FreeGiftSettings::class);
    $gift->enabled = false;
    $gift->save();
    giftCart(5000);

    expect(giftOrder()->items->contains('is_free_gift', true))->toBeFalse();
});

test('seuil à 0 (non renseigné) : fonction inactive', function () {
    $gift = app(FreeGiftSettings::class);
    $gift->threshold_ttc = 0;
    $gift->save();
    giftCart(5000);

    expect(app(CartService::class)->freeGift(5000))->toMatchArray(['active' => false, 'earned' => false])
        ->and(giftOrder()->items->contains('is_free_gift', true))->toBeFalse();
});

test('une seule pâtisserie offerte même au double du seuil', function () {
    giftCart(3000, 2);

    expect(giftOrder()->items->where('is_free_gift', true))->toHaveCount(1);
});

test('le poids de la pâtisserie offerte compte dans le port (tranche supérieure)', function () {
    giftCart(3000, 1, 900); // 900 g + 200 g offerts = 1,1 kg

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart());

    expect($totals['shipping_ttc'])->toBe(1200)
        ->and($totals['total_ttc'])->toBe(4200);
});

test('le seuil porte sur le montant après remise pro, hors frais de port', function () {
    $pro = User::factory()->pro()->create();
    $pro->forceFill(['discount_percent' => 10])->save();
    $this->actingAs($pro);
    giftCart(3000); // 27 € après remise + 6 € de port

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart());

    expect($totals['subtotal_ttc'])->toBe(2700)
        ->and($totals['free_gift'])->toMatchArray(['earned' => false, 'missing_ttc' => 300]);
});

test('commande saisie dans le back-office : pâtisserie offerte ajoutée automatiquement', function () {
    Mail::fake();
    $client = User::factory()->create();
    $product = Product::factory()->create(['price_ttc' => 1500, 'vat_rate' => 5.5, 'shipping_weight_g' => 300]);

    $order = app(CreateManualOrderAction::class)->execute(
        adminId: Admin::factory()->create()->id,
        user: $client,
        lines: [['product_id' => $product->id, 'quantity' => 2]],
        deliveryMethod: DeliveryMethod::ChronopostRelay,
        delivery: ['relay_name' => 'Relais Avranches', 'relay_postal_code' => '50300'],
        billing: ['line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches'],
        paymentMethod: PaymentMethod::Stripe,
    );

    expect($order->items->where('is_free_gift', true))->toHaveCount(1);
});

test('panier : montant manquant affiché, puis ligne « Offert » une fois le seuil atteint', function () {
    giftCart(2000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSee('Plus que 10,00 €')
        ->assertDontSee('Offert (0,00 €)');

    giftCart(1000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSee('Pâtisserie offerte × 1')
        ->assertSee('Offert (0,00 €)')
        ->assertDontSee('Plus que');
});

test('Stripe : la ligne offerte n\'est pas envoyée et le montant reste exact', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $stripeHttp = new FakeStripeHttpClient(['id' => 'cs_test_new', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_new']);
    ApiRequestor::setHttpClient($stripeHttp);

    $order = Order::factory()->create([
        'status' => OrderStatus::PendingPayment, 'payment_method' => PaymentMethod::Stripe,
        'subtotal_ttc' => 3000, 'shipping_ttc' => 600, 'total_ttc' => 3600,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'unit_price_ttc' => 3000, 'quantity' => 1, 'line_total_ttc' => 3000]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => null, 'product_name' => 'Pâtisserie offerte', 'is_free_gift' => true, 'unit_price_ttc' => 0, 'quantity' => 1, 'line_total_ttc' => 0, 'line_total_ht' => 0]);

    $this->get(route('checkout.stripe.start', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_new');

    $lines = $stripeHttp->requests[0]['params']['line_items'];
    expect($lines)->toHaveCount(2)
        ->and($lines[0]['price_data']['unit_amount'])->toBe(3000)
        ->and($lines[1]['price_data']['unit_amount'])->toBe(600);

    ApiRequestor::setHttpClient(null);
});

test('facture : ligne offerte à 0 €, absente du récapitulatif de TVA', function () {
    Storage::fake('local');
    giftCart(3000);

    $snapshot = app(IssueInvoiceAction::class)->execute(giftOrder())->snapshot;
    $giftLine = collect($snapshot['lines'])->firstWhere('free_gift', true);

    expect($giftLine)->toMatchArray(['designation' => 'Pâtisserie offerte', 'quantity' => 1, 'unit_price_ht' => 0, 'total_ht' => 0])
        ->and(collect($snapshot['vat_summary'])->pluck('rate')->all())->toEqual([5.5, 20]);
});

test('admin : montant saisi en euros, stocké en centimes', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ManageFreeGiftSettings::class)
        ->assertFormSet(['enabled' => true, 'threshold_ttc' => 30.0, 'weight_g' => 200])
        ->fillForm(['enabled' => true, 'threshold_ttc' => '45.50', 'label' => 'Pâtisserie offerte', 'weight_g' => 150])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(FreeGiftSettings::class)->refresh();
    expect($settings->threshold_ttc)->toBe(4550)
        ->and($settings->weight_g)->toBe(150);
});

test('admin : activation refusée sans montant ni poids', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ManageFreeGiftSettings::class)
        ->fillForm(['enabled' => true, 'threshold_ttc' => '0', 'label' => 'Pâtisserie offerte', 'weight_g' => 0])
        ->call('save')
        ->assertHasFormErrors(['threshold_ttc', 'weight_g']);
});

test('admin : la page est refusée sans connexion admin', function () {
    $this->get('/admin/manage-free-gift-settings')->assertRedirect();
});
