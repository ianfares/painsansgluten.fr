<?php

declare(strict_types=1);

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Exceptions\Orders\InvalidOrderTransition;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Livewire\CheckoutWizard;
use App\Mail\OrderReadyForPickupMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderStateMachine;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** Réponse BAN : l'adresse du client est à Avranches. */
function banAvranches(): array
{
    return ['features' => [['geometry' => ['coordinates' => [-1.3570, 48.6844]], 'properties' => ['score' => 0.9]]]];
}

/** Tunnel rempli jusqu'à l'étape 2, panier 2 × 10 € (port Chronopost : 6 € TTC). */
function pickupCheckout(): Testable
{
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->pickup_max_distance_km = 50;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    app(CartService::class)->add(Product::factory()->create(['price_ttc' => 1000, 'shipping_weight_g' => 300]), 2);

    return Livewire::test(CheckoutWizard::class)
        ->set('email', 'retrait@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2);
}

function payNow(Testable $component): Testable
{
    return $component->call('goToStep', 3)->call('goToStep', 4)
        ->set('cgvAccepted', true)->set('paymentMethod', 'bank_transfer')
        ->call('pay');
}

beforeEach(function () {
    Http::preventStrayRequests();
    // Séquence : la BAN répond « Avranches » tant que le test ne la remplace pas.
    $this->ban = Http::fakeSequence('api-adresse.data.gouv.fr/*')->whenEmpty(Http::response(banAvranches()));

    // Granville ≈ 24 km d'Avranches ; Caen ≈ 90 km ; un point inactif ; un point non géocodé.
    $this->near = PickupPoint::factory()->create(['name' => 'Épicerie de Granville', 'latitude' => 48.8378, 'longitude' => -1.5975, 'is_active' => true, 'opening_hours' => 'Mardi-samedi 9h-19h']);
    $this->far = PickupPoint::factory()->create(['name' => 'Fromagerie de Caen', 'latitude' => 49.1829, 'longitude' => -0.3707, 'is_active' => true]);
    PickupPoint::factory()->create(['name' => 'Boutique fermée', 'latitude' => 48.70, 'longitude' => -1.36, 'is_active' => false]);
    PickupPoint::factory()->create(['name' => 'Adresse inconnue', 'latitude' => null, 'longitude' => null, 'is_active' => true]);
});

test('étape 2 : Chronopost toujours proposé, seuls les commerçants actifs à 50 km ou moins, pas de labo pour un particulier', function () {
    pickupCheckout()
        ->assertSee('Chronopost Relais')
        ->assertSee('Retrait gratuit chez Épicerie de Granville')
        ->assertDontSee('Fromagerie de Caen')
        ->assertDontSee('Boutique fermée')
        ->assertDontSee('Adresse inconnue')
        ->assertDontSee('Retrait gratuit au laboratoire');
});

test('adresse client introuvable ou BAN injoignable : aucun commerçant, la commande Chronopost passe', function () {
    $this->ban->whenEmpty(Http::response([], 500));

    $component = pickupCheckout()->assertDontSee('Épicerie de Granville')
        ->set('relay_postal_code', '50300')->set('relay_name', 'Relais Avranches');
    payNow($component)->assertHasNoErrors();

    expect(Order::query()->sole()->delivery_method)->toBe(DeliveryMethod::ChronopostRelay);
});

test('retrait chez un commerçant : gratuit, snapshot du point, date de préparation identique', function () {
    payNow(pickupCheckout()->set('deliveryChoice', 'merchant_pickup:'.$this->near->id))->assertHasNoErrors();

    $order = Order::query()->sole();
    expect($order->delivery_method)->toBe(DeliveryMethod::MerchantPickup)
        ->and($order->shipping_ttc)->toBe(0)
        ->and($order->total_ttc)->toBe(2000)
        ->and($order->relay_id)->toBe('COMMERCANT-'.$this->near->id)
        ->and($order->relay_name)->toBe('Épicerie de Granville')
        ->and($order->relay_snapshot['opening_hours'])->toBe('Mardi-samedi 9h-19h')
        ->and($order->relay_snapshot['distance_km'])->toBeGreaterThan(20.0)->toBeLessThan(30.0)
        ->and($order->planned_ship_date)->not->toBeNull();
});

test('falsification : un point hors rayon, inactif ou inexistant envoyé par le navigateur est refusé', function (string $choice) {
    $choice = str_replace('{far}', (string) $this->far->id, $choice);

    payNow(pickupCheckout()->set('deliveryChoice', $choice))->assertHasErrors('deliveryChoice');

    expect(Order::query()->count())->toBe(0);
})->with(['hors rayon' => 'merchant_pickup:{far}', 'inexistant' => 'merchant_pickup:999999', 'sans point' => 'merchant_pickup', 'mode inconnu' => 'livraison_drone']);

test('retrait au laboratoire : refusé pour un particulier ou un pro non autorisé, gratuit pour un pro autorisé', function () {
    $this->actingAs(User::factory()->pro()->create());
    payNow(pickupCheckout()->set('deliveryChoice', 'lab_pickup'))->assertHasErrors('deliveryChoice');
    expect(Order::query()->count())->toBe(0);

    $shop = app(ShopSettings::class);
    $shop->address_line1 = '12 rue du Labo';
    $shop->save();
    $pro = User::factory()->pro()->create();
    $pro->forceFill(['lab_pickup_allowed' => true])->save();
    $this->actingAs($pro);

    payNow(pickupCheckout()->assertSee('Retrait gratuit au laboratoire')->set('deliveryChoice', 'lab_pickup'))->assertHasNoErrors();

    $order = Order::query()->sole();
    expect($order->delivery_method)->toBe(DeliveryMethod::LabPickup)
        ->and($order->shipping_ttc)->toBe(0)
        ->and($order->relay_id)->toBe('LABO')
        ->and($order->relay_snapshot['address_line1'])->toBe('12 rue du Labo');
});

test('retrait : « Prête au retrait » (email au client) puis « Retirée » ; « Expédiée » refusée', function () {
    Mail::fake();
    $machine = app(OrderStateMachine::class);
    $order = Order::factory()->create(['status' => OrderStatus::Paid, 'delivery_method' => DeliveryMethod::MerchantPickup, 'relay_snapshot' => ['name' => 'Épicerie', 'address_line1' => '1 place', 'postal_code' => '50400', 'city' => 'Granville']]);

    $machine->transition($order, OrderStatus::Preparing, 'admin');
    expect(fn () => $machine->transition($order, OrderStatus::Shipped, 'admin'))->toThrow(InvalidOrderTransition::class);

    $machine->transition($order, OrderStatus::ReadyForPickup, 'admin');
    Mail::assertQueued(OrderReadyForPickupMail::class, fn (OrderReadyForPickupMail $mail) => $mail->hasTo($order->email));

    expect($machine->transition($order, OrderStatus::PickedUp, 'admin')->status)->toBe(OrderStatus::PickedUp)
        ->and($order->fresh()->delivered_at)->not->toBeNull()
        ->and(OrderStatus::PickedUp->isPaidState())->toBeTrue();
});

test('Chronopost : « Prête au retrait » refusée', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    expect(fn () => app(OrderStateMachine::class)->transition($order, OrderStatus::ReadyForPickup, 'admin'))->toThrow(InvalidOrderTransition::class);
});

test('admin : « Prête au retrait » pour un retrait, « Expédier » pour Chronopost', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $pickup = Order::factory()->create(['status' => OrderStatus::Preparing, 'delivery_method' => DeliveryMethod::MerchantPickup]);
    $relay = Order::factory()->create(['status' => OrderStatus::Preparing]);

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('readyForPickup', $pickup)
        ->assertTableActionHidden('ship', $pickup)
        ->assertTableActionVisible('ship', $relay)
        ->assertTableActionHidden('readyForPickup', $relay);
});
