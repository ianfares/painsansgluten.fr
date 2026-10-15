<?php

declare(strict_types=1);

use App\Actions\Orders\CreateManualOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\ManualOrderNotAllowed;
use App\Livewire\CheckoutWizard;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** Produit non expédiable (décision client 2026-10-11) : commandable, mais uniquement en retrait. */
beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(['features' => [['geometry' => ['coordinates' => [-1.3570, 48.6844]], 'properties' => ['score' => 0.9]]]])]);

    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->pickup_max_distance_km = 50;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $this->cake = Product::factory()->notShippable()->create(['name' => 'Gâteau d\'anniversaire', 'is_published' => true, 'price_ttc' => 3000, 'shipping_weight_g' => 800]);
    $this->point = PickupPoint::factory()->create(['name' => 'Épicerie de Granville', 'latitude' => 48.8378, 'longitude' => -1.5975, 'is_active' => true]);
});

function pickupOnlyCheckout(): Testable
{
    return Livewire::test(CheckoutWizard::class)
        ->set('email', 'gateau@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2);
}

test('le produit non expédiable s\'ajoute au panier et y reste', function () {
    app(CartService::class)->add($this->cake, 1);

    $cart = app(CartService::class)->currentCart();
    expect(app(CartService::class)->validItems($cart)['items'])->toHaveCount(1);
});

test('tunnel : Chronopost non proposé, retrait chez le commerçant accepté sans frais de port', function () {
    app(CartService::class)->add($this->cake, 1);

    $component = pickupOnlyCheckout()
        ->assertSee('disponible uniquement en retrait')
        ->assertDontSee('Chronopost Relais')
        ->assertSee('Retrait gratuit chez Épicerie de Granville');

    // Chronopost forcé par une requête trafiquée : refusé.
    $component->set('deliveryChoice', 'chronopost_relay')->set('relay_postal_code', '50300')->set('relay_name', 'Relais Avranches')
        ->call('goToStep', 3)->assertHasErrors('deliveryChoice');

    $component->set('deliveryChoice', 'merchant_pickup:'.$this->point->id)->call('goToStep', 3)->call('goToStep', 4)
        ->set('cgvAccepted', true)->set('paymentMethod', 'stripe')->call('pay')->assertHasNoErrors();

    $order = Order::query()->sole();
    expect($order->delivery_method)->toBe(DeliveryMethod::MerchantPickup)
        ->and($order->shipping_ttc)->toBe(0)
        ->and($order->total_ttc)->toBe(3000);
});

test('aucun point de retrait à proximité : le client est prévenu', function () {
    $this->point->update(['is_active' => false]);
    app(CartService::class)->add($this->cake, 1);

    pickupOnlyCheckout()->assertSee('retirez ce produit du panier');
});

test('commande manuelle en Chronopost avec un produit non expédiable : refusée ; au labo pour un pro autorisé : acceptée', function () {
    $pro = User::factory()->pro()->create();
    $pro->forceFill(['lab_pickup_allowed' => true])->save();
    $args = [
        'adminId' => Admin::factory()->create()->id,
        'user' => $pro,
        'lines' => [['product_id' => $this->cake->id, 'quantity' => 1]],
        'deliveryMethod' => DeliveryMethod::ChronopostRelay,
        'delivery' => ['relay_name' => 'Relais Avranches', 'relay_postal_code' => '50300'],
        'billing' => ['line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches'],
        'paymentMethod' => PaymentMethod::BankTransfer,
    ];

    expect(fn () => app(CreateManualOrderAction::class)->execute(...$args))->toThrow(ManualOrderNotAllowed::class);

    $order = app(CreateManualOrderAction::class)->execute(...[...$args, 'deliveryMethod' => DeliveryMethod::LabPickup, 'delivery' => []]);
    expect($order->delivery_method)->toBe(DeliveryMethod::LabPickup);
});
