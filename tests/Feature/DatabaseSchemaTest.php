<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ClosedDate;
use App\Models\FaqItem;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\StripeEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('toutes les factories créent un enregistrement valide', function () {
    expect(Category::factory()->create())->toBeInstanceOf(Category::class)
        ->and(Product::factory()->create())->toBeInstanceOf(Product::class)
        ->and(Cart::factory()->create())->toBeInstanceOf(Cart::class)
        ->and(CartItem::factory()->create())->toBeInstanceOf(CartItem::class)
        ->and(Order::factory()->create())->toBeInstanceOf(Order::class)
        ->and(OrderItem::factory()->create())->toBeInstanceOf(OrderItem::class)
        ->and(OrderStatusHistory::factory()->create())->toBeInstanceOf(OrderStatusHistory::class)
        ->and(Payment::factory()->create())->toBeInstanceOf(Payment::class)
        ->and(StripeEvent::factory()->create())->toBeInstanceOf(StripeEvent::class)
        ->and(Invoice::factory()->create())->toBeInstanceOf(Invoice::class)
        ->and(InvoiceSequence::factory()->create())->toBeInstanceOf(InvoiceSequence::class)
        ->and(ShippingRate::factory()->create())->toBeInstanceOf(ShippingRate::class)
        ->and(ClosedDate::factory()->create())->toBeInstanceOf(ClosedDate::class)
        ->and(Page::factory()->create())->toBeInstanceOf(Page::class)
        ->and(FaqItem::factory()->create())->toBeInstanceOf(FaqItem::class)
        ->and(Address::factory()->create())->toBeInstanceOf(Address::class);
});

test('un produit appartient à une catégorie et une catégorie a plusieurs produits', function () {
    $category = Category::factory()->create();
    Product::factory()->count(3)->for($category)->create();

    expect($category->products)->toHaveCount(3)
        ->and($category->products->first()->category->is($category))->toBeTrue();
});

test('une commande a plusieurs lignes, un historique de statuts et des factures', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->count(2)->for($order)->create();
    OrderStatusHistory::factory()->for($order)->create();
    Invoice::factory()->for($order)->create();

    expect($order->items)->toHaveCount(2)
        ->and($order->statusHistories)->toHaveCount(1)
        ->and($order->invoices)->toHaveCount(1);
});

test('le statut de commande est casté en enum OrderStatus', function () {
    $order = Order::factory()->create();

    expect($order->status)->toBeInstanceOf(OrderStatus::class)
        ->and($order->status)->toBe(OrderStatus::PendingPayment);
});

test('le numéro de commande est unique en base', function () {
    $order = Order::factory()->create();

    expect(fn () => Order::factory()->create(['number' => $order->number]))
        ->toThrow(QueryException::class);
});

test('event_id Stripe est unique en base (idempotence webhook)', function () {
    $event = StripeEvent::factory()->create();

    expect(fn () => StripeEvent::factory()->create(['event_id' => $event->event_id]))
        ->toThrow(QueryException::class);
});

test('un même produit ne peut pas avoir deux lignes dans le même panier', function () {
    $cart = Cart::factory()->create();
    $product = Product::factory()->create();
    CartItem::factory()->for($cart)->for($product)->create();

    expect(fn () => CartItem::factory()->for($cart)->for($product)->create())
        ->toThrow(QueryException::class);
});

test('un utilisateur a des adresses et des commandes', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->create();
    Order::factory()->create(['user_id' => $user->id]);

    expect($user->addresses)->toHaveCount(1)
        ->and($user->orders)->toHaveCount(1);
});

test('migrate:fresh --seed s\'exécute sans erreur', function () {
    $exitCode = Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

    expect($exitCode)->toBe(0);
});

test('la table redirects a été supprimée (T27-L4)', function () {
    expect(Schema::hasTable('redirects'))->toBeFalse();
});
