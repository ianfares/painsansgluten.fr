<?php

declare(strict_types=1);

use App\Enums\DeliveryMethod;
use App\Livewire\CartWidget;
use App\Livewire\CheckoutWizard;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Interrupteur Chronopost du BO (24/10/2026) : coupé, le client ne peut
 * choisir qu'un retrait chez un commerçant ; le serveur refuse Chronopost
 * même si le navigateur l'envoie.
 */
beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->pickup_max_distance_km = 50;
    $settings->minimum_order_chronopost_ttc = 0;
    $settings->minimum_order_pickup_ttc = 0;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    Http::preventStrayRequests();
    // BAN : l'adresse du client est à Avranches ; un commerçant à Granville (≈ 24 km).
    $this->ban = Http::fakeSequence('api-adresse.data.gouv.fr/*')
        ->whenEmpty(Http::response(['features' => [['geometry' => ['coordinates' => [-1.3570, 48.6844]], 'properties' => ['score' => 0.9]]]]));
    $this->point = PickupPoint::factory()->create(['name' => 'Épicerie de Granville', 'latitude' => 48.8378, 'longitude' => -1.5975, 'is_active' => true]);

    app(CartService::class)->add(Product::factory()->create(['price_ttc' => 1000, 'shipping_weight_g' => 300]), 2);
});

function disableChronopost(): void
{
    $settings = app(ShippingSettings::class);
    $settings->chronopost_enabled = false;
    $settings->save();
}

function toggleCheckoutAtStep2(): Testable
{
    return Livewire::test(CheckoutWizard::class)
        ->set('email', 'retrait@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2);
}

function togglePay(Testable $component): Testable
{
    return $component->call('goToStep', 3)->call('goToStep', 4)
        ->set('cgvAccepted', true)->set('paymentMethod', 'stripe')
        ->call('pay');
}

test('Chronopost est activé par défaut (migration de réglages)', function () {
    expect(app(ShippingSettings::class)->chronopost_enabled)->toBeTrue();

    toggleCheckoutAtStep2()->assertSet('deliveryChoice', 'chronopost_relay')->assertSee('Chronopost Relais');
});

test('coupé : Chronopost n\'est plus proposé, seul le retrait chez le commerçant', function () {
    disableChronopost();

    toggleCheckoutAtStep2()
        ->assertSet('deliveryChoice', '')
        ->assertSee('momentanément suspendue')
        ->assertSee('Retrait gratuit chez Épicerie de Granville')
        ->assertDontSee('Expédition en point relais, partout en France')
        ->assertDontSee('Code postal du relais');
});

test('coupé : un choix Chronopost envoyé par le navigateur est refusé, aucune commande', function () {
    disableChronopost();

    $component = toggleCheckoutAtStep2()
        ->set('deliveryChoice', 'chronopost_relay')
        ->set('relay_postal_code', '50300')->set('relay_name', 'Relais Avranches');

    togglePay($component)->assertHasErrors('deliveryChoice');

    expect(Order::query()->count())->toBe(0);
});

test('coupé : la commande en retrait chez le commerçant passe, sans frais de port', function () {
    disableChronopost();

    togglePay(toggleCheckoutAtStep2()->set('deliveryChoice', 'merchant_pickup:'.$this->point->id))->assertHasNoErrors();

    $order = Order::query()->sole();
    expect($order->delivery_method)->toBe(DeliveryMethod::MerchantPickup)
        ->and($order->shipping_ttc)->toBe(0)
        ->and($order->total_ttc)->toBe(2000);
});

test('coupé : aucun commerçant dans le rayon, le client est prévenu qu\'on ne peut pas le livrer', function () {
    disableChronopost();
    $this->ban->whenEmpty(Http::response([], 500));

    toggleCheckoutAtStep2()
        ->assertSee('nous ne pouvons pas encore vous livrer')
        ->assertDontSee('Retrait gratuit chez Épicerie de Granville');
});

test('panier : frais de port Chronopost affichés quand activé, retrait annoncé quand coupé', function () {
    Livewire::test(CartWidget::class)->assertSee('Frais de port')->assertDontSee('Livraison Chronopost momentanément suspendue');

    disableChronopost();

    Livewire::test(CartWidget::class)
        ->assertSee('Livraison Chronopost momentanément suspendue')
        ->assertDontSee('Frais de port')
        ->assertSee('20,00 €');
});
