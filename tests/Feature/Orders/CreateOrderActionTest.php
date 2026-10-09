<?php

declare(strict_types=1);

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;

beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->shipping_vat_rate = 20.0;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);
});

function customerPayload(): array
{
    return [
        'customer' => ['email' => 'camille@example.com', 'first_name' => 'Camille', 'last_name' => 'Durand', 'phone' => '0612345678'],
        'billing' => ['first_name' => 'Camille', 'last_name' => 'Durand', 'line1' => '12 rue des Lilas', 'postal_code' => '50300', 'city' => 'Avranches'],
        'relay' => ['relay_id' => 'REL-1', 'relay_name' => 'Tabac de la Gare', 'relay_snapshot' => ['name' => 'Tabac de la Gare']],
    ];
}

test('crée une commande avec un numéro séquentiel, un token et un snapshot complet', function () {
    $product = Product::factory()->create(['price_ttc' => 690, 'shipping_weight_g' => 400]);
    $cartService = app(CartService::class);
    $cart = $cartService->currentCart();
    $cartService->add($product, 2);

    $payload = customerPayload();
    $order = app(CreateOrderAction::class)->execute($cart, $payload['customer'], $payload['billing'], $payload['relay'], PaymentMethod::Stripe);

    expect($order->number)->toMatch('/^C\d{4}-\d{5}$/')
        ->and($order->token)->toHaveLength(40)
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and($order->relay_name)->toBe('Tabac de la Gare')
        ->and($order->cgv_accepted_at)->not->toBeNull();
});

test('les numéros de commande sont séquentiels sans trou', function () {
    $product = Product::factory()->create();
    $service = app(CartService::class);
    $payload = customerPayload();

    $numbers = collect(range(1, 3))->map(function () use ($product, $service, $payload) {
        $service->add($product, 1);
        $order = app(CreateOrderAction::class)->execute($service->currentCart(), $payload['customer'], $payload['billing'], $payload['relay'], PaymentMethod::Stripe);

        return (int) substr($order->number, -5);
    });

    expect($numbers->toArray())->toBe([$numbers[0], $numbers[0] + 1, $numbers[0] + 2]);
});

test('les totaux sont toujours recalculés côté serveur, jamais depuis une valeur cliente', function () {
    $product = Product::factory()->create(['price_ttc' => 1000, 'shipping_weight_g' => 300]);
    $service = app(CartService::class);
    $cart = $service->currentCart();
    $service->add($product, 3);

    $payload = customerPayload();
    $order = app(CreateOrderAction::class)->execute($cart, $payload['customer'], $payload['billing'], $payload['relay'], PaymentMethod::Stripe);

    // 3 x 10,00 € = 30,00 € + 5,90 € de port = 35,90 €, indépendamment de toute valeur externe.
    expect($order->subtotal_ttc)->toBe(3000)
        ->and($order->shipping_ttc)->toBe(600)
        ->and($order->total_ttc)->toBe(3600);
});

test('un panier vide ne peut pas créer de commande', function () {
    $service = app(CartService::class);
    $cart = $service->currentCart();
    $payload = customerPayload();

    expect(fn () => app(CreateOrderAction::class)->execute($cart, $payload['customer'], $payload['billing'], $payload['relay'], PaymentMethod::Stripe))
        ->toThrow(RuntimeException::class);
});

test('le panier est vidé après création de la commande', function () {
    $product = Product::factory()->create();
    $service = app(CartService::class);
    $cart = $service->currentCart();
    $service->add($product, 1);
    $payload = customerPayload();

    app(CreateOrderAction::class)->execute($cart, $payload['customer'], $payload['billing'], $payload['relay'], PaymentMethod::Stripe);

    expect($cart->fresh()->items()->count())->toBe(0);
});
