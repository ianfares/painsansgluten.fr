<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Events\Orders\OrderCancelled;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderShipped;
use App\Exceptions\Orders\InvalidOrderTransition;
use App\Models\Order;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Support\Facades\Event;

test('toutes les transitions autorisées par PLAN §9.2 réussissent', function (OrderStatus $from, OrderStatus $to) {
    $order = Order::factory()->create(['status' => $from]);

    $updated = app(OrderStateMachine::class)->transition($order, $to, 'admin', 1);

    expect($updated->status)->toBe($to);
})->with([
    [OrderStatus::PendingPayment, OrderStatus::Paid],
    [OrderStatus::PendingPayment, OrderStatus::PaymentFailed],
    [OrderStatus::PendingPayment, OrderStatus::Cancelled],
    [OrderStatus::PaymentFailed, OrderStatus::Paid],
    [OrderStatus::PaymentFailed, OrderStatus::Cancelled],
    [OrderStatus::Paid, OrderStatus::Preparing],
    [OrderStatus::Paid, OrderStatus::Refunded],
    [OrderStatus::Preparing, OrderStatus::Shipped],
    [OrderStatus::Preparing, OrderStatus::Refunded],
    [OrderStatus::Shipped, OrderStatus::Delivered],
    [OrderStatus::Shipped, OrderStatus::Refunded],
    [OrderStatus::Delivered, OrderStatus::Refunded],
]);

test('toutes les transitions interdites sont rejetées', function (OrderStatus $from, OrderStatus $to) {
    $order = Order::factory()->create(['status' => $from]);

    expect(fn () => app(OrderStateMachine::class)->transition($order, $to, 'admin'))
        ->toThrow(InvalidOrderTransition::class);

    expect($order->fresh()->status)->toBe($from);
})->with([
    [OrderStatus::PendingPayment, OrderStatus::Shipped],
    [OrderStatus::Paid, OrderStatus::PendingPayment],
    [OrderStatus::Cancelled, OrderStatus::Paid],
    [OrderStatus::Refunded, OrderStatus::Paid],
    [OrderStatus::Delivered, OrderStatus::Preparing],
]);

test('chaque transition est historisée avec acteur et commentaire', function () {
    $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

    app(OrderStateMachine::class)->transition($order, OrderStatus::Paid, 'system', null, 'Webhook Stripe');

    $history = $order->statusHistories()->latest('id')->first();
    expect($history->from)->toBe('pending_payment')
        ->and($history->to)->toBe('paid')
        ->and($history->actor_type)->toBe('system')
        ->and($history->comment)->toBe('Webhook Stripe');
});

test('le passage à paid renseigne paid_at et déclenche OrderPaid une seule fois', function () {
    Event::fake();
    $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

    $updated = app(OrderStateMachine::class)->transition($order, OrderStatus::Paid, 'system');

    expect($updated->paid_at)->not->toBeNull();
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('le passage à shipped renseigne shipped_at et déclenche OrderShipped', function () {
    Event::fake();
    $order = Order::factory()->create(['status' => OrderStatus::Preparing]);

    $updated = app(OrderStateMachine::class)->transition($order, OrderStatus::Shipped, 'admin');

    expect($updated->shipped_at)->not->toBeNull();
    Event::assertDispatched(OrderShipped::class);
});

test('le passage à cancelled déclenche OrderCancelled', function () {
    Event::fake();
    $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

    app(OrderStateMachine::class)->transition($order, OrderStatus::Cancelled, 'system');

    Event::assertDispatched(OrderCancelled::class);
});
