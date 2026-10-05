<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;

beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->max_quantity_per_line = 5;
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->save();

    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ttc' => 590]);
});

test('ajouter un produit crée une ligne de panier', function () {
    $product = Product::factory()->create();

    $item = app(CartService::class)->add($product, 2);

    expect($item->quantity)->toBe(2)
        ->and($item->product_id)->toBe($product->id);
});

test('ajouter deux fois le même produit cumule la quantité', function () {
    $product = Product::factory()->create();
    $service = app(CartService::class);

    $service->add($product, 2);
    $item = $service->add($product, 1);

    expect($item->quantity)->toBe(3);
});

test('la quantité est plafonnée au maximum paramétré', function () {
    $product = Product::factory()->create();

    $item = app(CartService::class)->add($product, 99);

    expect($item->quantity)->toBe(5);
});

test('mettre la quantité à 0 supprime la ligne', function () {
    $product = Product::factory()->create();
    $service = app(CartService::class);
    $item = $service->add($product, 2);

    $service->updateQuantity($item, 0);

    expect($item->fresh())->toBeNull();
});

test('un produit devenu indisponible est retiré du panier avec son nom signalé', function () {
    $product = Product::factory()->create(['name' => 'Pain retiré']);
    $service = app(CartService::class);
    $service->add($product, 1);
    $product->update(['is_available' => false]);

    $cart = $service->currentCart();
    ['items' => $items, 'removed' => $removed] = $service->validItems($cart);

    expect($items)->toHaveCount(0)
        ->and($removed)->toContain('Pain retiré');
});

test('les totaux calculent sous-total, port et date d\'expédition', function () {
    $product = Product::factory()->create(['price_ttc' => 500, 'shipping_weight_g' => 300]);
    $service = app(CartService::class);
    $service->add($product, 2);

    $totals = $service->totals($service->currentCart());

    expect($totals['subtotal_ttc'])->toBe(1000)
        ->and($totals['shipping_ttc'])->toBe(590)
        ->and($totals['total_ttc'])->toBe(1590)
        ->and($totals['planned_ship_date'])->not->toBeNull()
        ->and($totals['shipping_error'])->toBeNull();
});

test('la fusion à la connexion additionne les lignes du panier invité dans celui du client', function () {
    $product = Product::factory()->create();
    $service = app(CartService::class);
    $service->add($product, 2);

    $user = User::factory()->create();
    $service->mergeIntoUser($user);

    $this->actingAs($user);
    $userCart = $service->currentCart();

    expect($userCart->user_id)->toBe($user->id)
        ->and($userCart->items()->where('product_id', $product->id)->first()->quantity)->toBe(2);
});

test('la page panier répond 200', function () {
    $this->get(route('panier'))->assertOk();
});
