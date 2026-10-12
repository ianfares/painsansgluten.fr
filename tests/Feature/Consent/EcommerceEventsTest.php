<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\AddToCartButton;
use App\Livewire\CartWidget;
use App\Livewire\CheckoutWizard;
use App\Livewire\StripePaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use Livewire\Livewire;

/** Événements e-commerce GA4 dans le dataLayer (T22). */
function enableGtm(): void
{
    config(['services.gtm.id' => 'GTM-KFK55VB8', 'services.gtm.enabled_outside_production' => true]);
}

beforeEach(function () {
    $this->product = Product::factory()->create([
        'name' => 'Pain de mie', 'reference' => 'P150', 'price_ttc' => 1000, 'is_published' => true,
        'category_id' => Category::factory()->create(['name' => 'Pains'])->id,
    ]);
});

test('GTM inactif : aucun événement e-commerce', function () {
    $this->get(route('products.show', $this->product))->assertOk()->assertDontSee('view_item');

    Livewire::test(AddToCartButton::class, ['product' => $this->product])->call('add')->assertNotDispatched('analytics-event');
});

test('fiche produit : view_item avec référence, catégorie et prix remisé du client connecté', function () {
    enableGtm();
    $user = User::factory()->create();
    $user->forceFill(['discount_percent' => 10])->save();

    $html = $this->actingAs($user)->get(route('products.show', $this->product))->assertOk()->getContent();

    expect($html)->toContain('"event":"view_item"')
        ->toContain('"item_id":"P150"')
        ->toContain('"item_category":"Pains"')
        ->toContain('"price":9')
        ->toContain('"currency":"EUR"');
});

test('boutique : view_item_list', function () {
    enableGtm();

    expect($this->get(route('boutique.index'))->assertOk()->getContent())
        ->toContain('"event":"view_item_list"')
        ->toContain('"item_list_name":"Boutique"');
});

test('ajout, changement de quantité et retrait du panier : add_to_cart / remove_from_cart', function () {
    enableGtm();

    Livewire::test(AddToCartButton::class, ['product' => $this->product])
        ->set('quantity', 2)->call('add')
        ->assertDispatched('analytics-event', fn (string $event, array $params) => $params['name'] === 'add_to_cart'
            && $params['ecommerce']['value'] == 20 && $params['ecommerce']['items'][0]['quantity'] === 2);

    $item = app(CartService::class)->currentCart()->items()->sole();

    Livewire::test(CartWidget::class)->call('updateQuantity', $item->id, 1)
        ->assertDispatched('analytics-event', fn (string $event, array $params) => $params['name'] === 'remove_from_cart' && $params['ecommerce']['items'][0]['quantity'] === 1);

    Livewire::test(CartWidget::class)->call('removeItem', $item->id)
        ->assertDispatched('analytics-event', fn (string $event, array $params) => $params['name'] === 'remove_from_cart');
});

test('page panier : view_cart', function () {
    enableGtm();
    app(CartService::class)->add($this->product, 3);

    expect($this->get('/panier')->assertOk()->getContent())->toContain('"event":"view_cart"')->toContain('"value":30');
});

test('purchase : une seule fois, uniquement pour une commande carte payée', function () {
    enableGtm();
    $order = Order::factory()->create(['status' => OrderStatus::Paid, 'payment_method' => PaymentMethod::Stripe, 'total_ttc' => 1600, 'total_vat' => 100, 'shipping_ttc' => 600]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'product_reference' => 'P150', 'unit_price_ttc' => 1000, 'unit_discount_ttc' => 0, 'quantity' => 1, 'line_total_ttc' => 1000]);

    Livewire::test(StripePaymentStatus::class, ['token' => $order->token])
        ->assertDispatched('analytics-event', fn (string $event, array $params) => $params['name'] === 'purchase'
            && $params['ecommerce']['transaction_id'] === $order->number
            && $params['ecommerce']['value'] == 16
            && $params['ecommerce']['shipping'] == 6
            && $params['ecommerce']['items'][0]['item_id'] === 'P150');

    expect($order->fresh()->ga_purchase_sent)->toBeTrue();

    // Rechargement de la confirmation : pas de second purchase.
    Livewire::test(StripePaymentStatus::class, ['token' => $order->token])->assertNotDispatched('analytics-event');
});

test('purchase : rien pour une commande en attente ou payée par virement', function (OrderStatus $status, PaymentMethod $method) {
    enableGtm();
    $order = Order::factory()->create(['status' => $status, 'payment_method' => $method]);

    Livewire::test(StripePaymentStatus::class, ['token' => $order->token])->assertNotDispatched('analytics-event');
    expect($order->fresh()->ga_purchase_sent)->toBeFalse();
})->with([
    'carte en attente' => [OrderStatus::PendingPayment, PaymentMethod::Stripe],
    'virement payé' => [OrderStatus::Paid, PaymentMethod::BankTransfer],
]);

test('tunnel : begin_checkout à l\'ouverture', function () {
    enableGtm();
    app(CartService::class)->add($this->product, 1);

    Livewire::test(CheckoutWizard::class)
        ->assertDispatched('analytics-event', fn (string $event, array $params) => $params['name'] === 'begin_checkout' && $params['ecommerce']['items'][0]['item_id'] === 'P150');
});
