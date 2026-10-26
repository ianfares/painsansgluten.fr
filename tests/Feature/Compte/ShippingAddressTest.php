<?php

declare(strict_types=1);

use App\Livewire\CheckoutWizard;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Client avec une adresse de facturation à Avranches. */
function customerWithBilling(): User
{
    $user = User::factory()->create();
    $user->addresses()->create(['type' => 'billing', 'first_name' => 'Jeanne', 'last_name' => 'Dupont', 'line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches']);

    return $user;
}

test('mon compte : l\'adresse de livraison est facultative, distincte de la facturation, et peut redevenir identique', function () {
    $user = customerWithBilling();
    $this->actingAs($user)->get(route('compte.informations'))->assertOk()->assertSee('Adresse de livraison')->assertSee('Identique à l\'adresse de facturation', false);

    $this->put(route('compte.shipping-address.update'), [
        'same_as_billing' => 0,
        'shipping_first_name' => 'Jeanne', 'shipping_last_name' => 'Dupont',
        'shipping_line1' => '8 rue de Caen', 'shipping_postal_code' => '14000', 'shipping_city' => 'Caen',
    ])->assertSessionHasNoErrors();

    expect($user->shippingAddress()->city)->toBe('Caen')
        ->and($user->billingAddress()->city)->toBe('Avranches');

    $this->put(route('compte.shipping-address.update'), ['same_as_billing' => 0, 'shipping_postal_code' => 'abc'])
        ->assertSessionHasErrors(['shipping_line1', 'shipping_postal_code', 'shipping_city']);

    $this->put(route('compte.shipping-address.update'), ['same_as_billing' => 1])->assertSessionHasNoErrors();
    expect($user->shippingAddress())->toBeNull()
        ->and($user->billingAddress()->city)->toBe('Avranches');
});

test('commande : adresse de livraison reprise du compte, enregistrée sur la commande et utilisée pour les points de retrait proches', function () {
    Http::preventStrayRequests();
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(['features' => [['geometry' => ['coordinates' => [-0.3707, 49.1829]], 'properties' => ['score' => 0.9]]]])]);
    PickupPoint::factory()->create(['name' => 'Épicerie de Caen', 'latitude' => 49.18, 'longitude' => -0.37, 'is_active' => true]);

    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $user = customerWithBilling();
    $user->addresses()->create(['type' => 'shipping', 'first_name' => 'Jeanne', 'last_name' => 'Dupont', 'line1' => '8 rue de Caen', 'postal_code' => '14000', 'city' => 'Caen']);
    $this->actingAs($user);
    app(CartService::class)->add(Product::factory()->create(['shipping_weight_g' => 300]), 1);

    $component = Livewire::test(CheckoutWizard::class)
        ->assertSet('shippingSameAsBilling', false)
        ->assertSet('shipping_city', 'Caen')
        ->assertSet('billing_city', 'Avranches')
        ->set('phone', '0612345678')
        ->call('goToStep', 2)
        ->assertSee('Épicerie de Caen')
        ->assertSet('relay_postal_code', '14000')
        ->assertSet('relay_city', 'Caen');

    // La recherche des points proches part de l'adresse de LIVRAISON (Caen), pas de la facturation.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'postcode=14000'));

    $component->set('deliveryChoice', 'chronopost_relay')->set('relay_postal_code', '33000')->set('relay_city', 'BORDEAUX')->call('searchRelays')->set('relay_id', '8339S')
        ->call('goToStep', 3)->call('goToStep', 4)
        ->set('cgvAccepted', true)->set('paymentMethod', 'stripe')
        ->call('pay')->assertHasNoErrors();

    $order = Order::query()->sole();
    expect($order->billing_city)->toBe('Avranches')
        ->and($order->shipping_city)->toBe('Caen')
        ->and($order->shipping_line1)->toBe('8 rue de Caen')
        ->and($order->hasDistinctShippingAddress())->toBeTrue();
});

test('commande : sans adresse de livraison distincte, rien n\'est enregistré en double', function () {
    $order = Order::factory()->create();

    expect($order->hasDistinctShippingAddress())->toBeFalse();
});

test('commande : une adresse de livraison incomplète bloque l\'étape 1', function () {
    app(CartService::class)->add(Product::factory()->create(), 1);

    Livewire::test(CheckoutWizard::class)
        ->set('email', 'a@example.com')->set('first_name', 'A')->set('last_name', 'B')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->set('shippingSameAsBilling', false)
        ->call('goToStep', 2)
        ->assertHasErrors(['shipping_line1', 'shipping_postal_code', 'shipping_city'])
        ->assertSet('step', 1);
});
