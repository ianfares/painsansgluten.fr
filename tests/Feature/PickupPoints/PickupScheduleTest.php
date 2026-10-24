<?php

declare(strict_types=1);

use App\Enums\DeliveryMethod;
use App\Filament\Resources\PickupPointResource\Pages\CreatePickupPoint;
use App\Livewire\CheckoutWizard;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Services\Shipping\ShippingDateCalculator;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Jour(s) et plage de retrait par commerçant (24/10/2026) : la date de
 * retrait suit les jours du commerçant, pas les jours d'expédition ; même
 * heure limite et même délai de fabrication.
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
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(['features' => [['geometry' => ['coordinates' => [-1.3570, 48.6844]], 'properties' => ['score' => 0.9]]]])]);

    // Lundi 26/10/2026 10 h : prête mardi 27 (délai 1 jour) ; retrait le vendredi seulement.
    $this->travelTo(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Paris'));
    $this->point = PickupPoint::factory()->create([
        'name' => 'Épicerie de Granville', 'latitude' => 48.8378, 'longitude' => -1.5975, 'is_active' => true,
        'pickup_weekdays' => ['friday'], 'pickup_from' => '09:00', 'pickup_until' => '12:30',
    ]);
});

test('plage horaire : « de 9 h à 12 h 30 », rien si incomplète', function () {
    expect(PickupPoint::formatPickupHours('09:00:00', '12:30:00'))->toBe('de 9 h à 12 h 30')
        ->and(PickupPoint::formatPickupHours('09:00', null))->toBeNull();
});

test('calcul : jours du commerçant au lieu des jours d\'expédition, vides = jours d\'expédition', function () {
    $calculator = app(ShippingDateCalculator::class);
    $now = CarbonImmutable::now();

    expect($calculator->forInstant($now)->toDateString())->toBe('2026-10-27')
        ->and($calculator->forInstant($now, ['friday'])->toDateString())->toBe('2026-10-30')
        ->and($calculator->forInstant($now, [])->toDateString())->toBe('2026-10-27');
});

test('calcul au paiement : jours figés dans le snapshot de la commande ; Chronopost inchangé', function () {
    $calculator = app(ShippingDateCalculator::class);
    $pickup = Order::factory()->create(['delivery_method' => DeliveryMethod::MerchantPickup, 'relay_snapshot' => ['pickup_weekdays' => ['friday']]]);
    $relay = Order::factory()->create(['relay_snapshot' => ['pickup_weekdays' => ['friday']]]);

    expect($calculator->forOrder($pickup, CarbonImmutable::now())->toDateString())->toBe('2026-10-30')
        ->and($calculator->forOrder($relay, CarbonImmutable::now())->toDateString())->toBe('2026-10-27');
});

test('tunnel : date et plage affichées sous le commerçant, commande datée du vendredi', function () {
    app(CartService::class)->add(Product::factory()->create(['price_ttc' => 1000, 'shipping_weight_g' => 300]), 2);

    $component = Livewire::test(CheckoutWizard::class)
        ->set('email', 'retrait@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2)
        ->assertSee('Retrait le vendredi 30 octobre, de 9 h à 12 h 30');

    $component->set('deliveryChoice', 'merchant_pickup:'.$this->point->id)
        ->call('goToStep', 3)->assertSee('Retrait le vendredi 30 octobre')
        ->call('goToStep', 4)->set('cgvAccepted', true)->set('paymentMethod', 'stripe')
        ->call('pay')->assertHasNoErrors();

    $order = Order::query()->sole();
    expect($order->planned_ship_date->toDateString())->toBe('2026-10-30')
        ->and($order->relay_snapshot['pickup_weekdays'])->toBe(['friday'])
        ->and($order->relay_snapshot['pickup_hours'])->toBe('de 9 h à 12 h 30');
});

test('admin : jours et plage de retrait enregistrés sur le point', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreatePickupPoint::class)->fillForm([
        'name' => 'Boulangerie Dupont', 'address_line1' => '1 place Littré', 'postal_code' => '50300', 'city' => 'Avranches',
        'opening_hours' => 'Mardi-samedi 9h-19h', 'is_active' => true, 'position' => 1,
        'pickup_weekdays' => ['wednesday'], 'pickup_from' => '14:00', 'pickup_until' => '18:00',
    ])->call('create')->assertHasNoFormErrors();

    $point = PickupPoint::query()->where('name', 'Boulangerie Dupont')->sole();
    expect($point->pickup_weekdays)->toBe(['wednesday'])
        ->and(PickupPoint::formatPickupHours($point->pickup_from, $point->pickup_until))->toBe('de 14 h à 18 h');
});

test('admin : plage refusée si la fin est avant le début', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreatePickupPoint::class)->fillForm([
        'name' => 'Boulangerie Dupont', 'address_line1' => '1 place Littré', 'postal_code' => '50300', 'city' => 'Avranches',
        'opening_hours' => 'Mardi-samedi 9h-19h', 'pickup_from' => '18:00', 'pickup_until' => '14:00',
    ])->call('create')->assertHasFormErrors(['pickup_until']);
});
