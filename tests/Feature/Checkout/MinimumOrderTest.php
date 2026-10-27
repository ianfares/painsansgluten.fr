<?php

declare(strict_types=1);

use App\Actions\Orders\CreateOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\OrderBelowMinimum;
use App\Filament\Pages\ManageShippingSettings;
use App\Livewire\CartWidget;
use App\Livewire\CheckoutWizard;
use App\Models\Admin;
use App\Models\CheckoutBlock;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Minimum de commande par mode de livraison (19/10/2026) : produits après
 * remise, hors frais de port ; Chronopost et retrait ont chacun leur montant.
 */
beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->pickup_max_distance_km = 50;
    $settings->minimum_order_chronopost_ttc = 2500;
    $settings->minimum_order_pickup_ttc = 1500;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    Http::preventStrayRequests();
    // BAN : l'adresse du client est à Avranches ; un commerçant à Granville (≈ 24 km).
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(['features' => [['geometry' => ['coordinates' => [-1.3570, 48.6844]], 'properties' => ['score' => 0.9]]]])]);
    $this->point = PickupPoint::factory()->create(['latitude' => 48.8378, 'longitude' => -1.5975, 'is_active' => true]);
});

function cartOf(int $priceTtc, int $quantity = 1): void
{
    app(CartService::class)->add(Product::factory()->create(['price_ttc' => $priceTtc, 'shipping_weight_g' => 300]), $quantity);
}

function minimumCheckoutAtStep2(): Testable
{
    return Livewire::test(CheckoutWizard::class)
        ->set('email', 'minimum@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2)
        ->set('relay_postal_code', '33000')->set('relay_city', 'BORDEAUX')->call('searchRelays')->set('relay_id', '8339S');
}

/** @return array{0: array<string, string>, 1: array<string, string>, 2: array<string, mixed>} */
function minimumOrderPayload(): array
{
    return [
        ['email' => 'minimum@example.com', 'first_name' => 'Jeanne', 'last_name' => 'Dupont', 'phone' => '0612345678'],
        ['first_name' => 'Jeanne', 'last_name' => 'Dupont', 'line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches'],
        ['relay_id' => 'REL-1', 'relay_name' => 'Relais', 'relay_snapshot' => ['name' => 'Relais']],
    ];
}

test('CreateOrderAction refuse une commande sous le minimum, sans créer de commande ni vider le panier', function () {
    cartOf(2499);
    $cart = app(CartService::class)->currentCart();
    [$customer, $billing, $relay] = minimumOrderPayload();

    expect(fn () => app(CreateOrderAction::class)->execute($cart, $customer, $billing, $relay, PaymentMethod::Stripe))
        ->toThrow(OrderBelowMinimum::class, 'il vous manque 0,01 €');

    expect(Order::query()->count())->toBe(0)
        ->and($cart->items()->count())->toBe(1);
});

test('le minimum exact est accepté (borne incluse)', function () {
    cartOf(2500);
    [$customer, $billing, $relay] = minimumOrderPayload();

    $order = app(CreateOrderAction::class)->execute(app(CartService::class)->currentCart(), $customer, $billing, $relay, PaymentMethod::Stripe);

    expect($order->subtotal_ttc)->toBe(2500);
});

test('les frais de port ne comptent pas dans le minimum', function () {
    cartOf(2000); // + 6 € de port TTC = 26 € au total, mais 20 € de produits < 25 €

    expect(app(CartService::class)->totals(app(CartService::class)->currentCart()))
        ->toMatchArray(['total_ttc' => 2600, 'minimum_ttc' => 2500, 'minimum_missing_ttc' => 500]);
});

test('chaque mode a son minimum : 20 € refusés en Chronopost, acceptés en retrait', function () {
    cartOf(1000, 2);

    minimumCheckoutAtStep2()
        ->assertSee('il vous manque 5,00 €')
        ->call('goToStep', 3)
        ->assertHasErrors('deliveryChoice')
        ->assertSet('step', 2)
        ->set('deliveryChoice', 'merchant_pickup:'.$this->point->id)
        ->call('goToStep', 3)
        ->assertHasNoErrors()
        ->call('goToStep', 4)
        ->set('cgvAccepted', true)->set('paymentMethod', 'stripe')
        ->call('pay');

    expect(Order::query()->sole()->delivery_method)->toBe(DeliveryMethod::MerchantPickup);
});

test('falsification : « Payer » appelé directement sous le minimum est refusé', function () {
    cartOf(1000);

    minimumCheckoutAtStep2()
        ->set('cgvAccepted', true)->set('paymentMethod', 'stripe')
        ->call('pay')
        ->assertHasErrors('deliveryChoice')
        ->assertSet('step', 2);

    expect(Order::query()->count())->toBe(0);
});

test('la commande manuelle du back-office n\'est pas soumise au minimum', function () {
    cartOf(500);
    [$customer, $billing, $relay] = minimumOrderPayload();

    $order = app(CreateOrderAction::class)->execute(app(CartService::class)->currentCart(), $customer, $billing, $relay, PaymentMethod::Stripe, enforceMinimum: false);

    expect($order->subtotal_ttc)->toBe(500);
});

test('panier : sous le plus petit minimum, message et bouton « Passer commande » désactivé', function () {
    cartOf(1000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSee('25,00 € en livraison Chronopost Relais, 15,00 € en retrait')
        ->assertSee('Il vous manque 5,00 €')
        ->assertDontSee(route('checkout'));
});

test('panier : entre les deux minimums, commande possible et mode limité signalé', function () {
    cartOf(2000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSee('Livraison Chronopost possible à partir de 25,00 €')
        ->assertSee(route('checkout'));
});

test('panier : au-dessus des deux minimums, aucun message', function () {
    cartOf(3000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('Minimum de commande')
        ->assertSee(route('checkout'));
});

test('minimum à 0 : aucune restriction', function () {
    $settings = app(ShippingSettings::class);
    $settings->minimum_order_chronopost_ttc = 0;
    $settings->save();
    cartOf(100);
    [$customer, $billing, $relay] = minimumOrderPayload();

    $order = app(CreateOrderAction::class)->execute(app(CartService::class)->currentCart(), $customer, $billing, $relay, PaymentMethod::Stripe);

    expect($order->subtotal_ttc)->toBe(100);
});

test('admin : les minimums se saisissent en euros et sont stockés en centimes', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ManageShippingSettings::class)
        ->assertFormSet(['minimum_order_chronopost_ttc' => 25.0, 'minimum_order_pickup_ttc' => 15.0])
        ->fillForm(['minimum_order_chronopost_ttc' => '18.50', 'minimum_order_pickup_ttc' => '12'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(ShippingSettings::class)->refresh();
    expect($settings->minimum_order_chronopost_ttc)->toBe(1850)
        ->and($settings->minimum_order_pickup_ttc)->toBe(1200);
});

test('admin : la page des paramètres d\'expédition est refusée sans connexion admin', function () {
    $this->get('/admin/manage-shipping-settings')->assertRedirect();
});

test('un refus pour minimum non atteint est compté une fois, puis rattaché à la commande passée ensuite', function () {
    cartOf(1000, 2);

    $wizard = minimumCheckoutAtStep2();
    $wizard->call('goToStep', 3)->assertHasErrors('deliveryChoice');
    $wizard->call('goToStep', 3)->assertHasErrors('deliveryChoice'); // second clic : pas de doublon

    $block = CheckoutBlock::query()->sole();
    expect($block->delivery_method)->toBe(DeliveryMethod::ChronopostRelay)
        ->and($block->subtotal_ttc)->toBe(2000)
        ->and($block->minimum_ttc)->toBe(2500)
        ->and($block->order_id)->toBeNull();

    // Le client complète son panier puis commande.
    cartOf(500);
    [$customer, $billing, $relay] = minimumOrderPayload();
    $order = app(CreateOrderAction::class)->execute(app(CartService::class)->currentCart(), $customer, $billing, $relay, PaymentMethod::Stripe);

    expect($block->fresh()->order_id)->toBe($order->id);
});

test('un échec d\'enregistrement du refus ne gêne pas le client', function () {
    Schema::drop('checkout_blocks');
    cartOf(1000, 2);

    minimumCheckoutAtStep2()->call('goToStep', 3)->assertHasErrors('deliveryChoice')->assertSee('il vous manque 5,00 €');
});
