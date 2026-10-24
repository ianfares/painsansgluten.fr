<?php

declare(strict_types=1);

use App\Actions\Orders\DeleteUnpaidOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\OrderDeletionNotAllowed;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Livewire\Livewire;

/**
 * BO « Supprimer » (24/10/2026) : commandes non réglées et sans facture
 * uniquement ; une commande payée ou facturée se conserve.
 */
function unpaidOrder(array $attributes = []): Order
{
    return Order::factory()->create([
        'status' => OrderStatus::PendingPayment,
        'payment_method' => PaymentMethod::BankTransfer,
        ...$attributes,
    ]);
}

test('supprime une commande non réglée avec ses lignes, paiements et historique', function () {
    $order = unpaidOrder();
    OrderItem::factory()->create(['order_id' => $order->id]);
    Payment::factory()->create(['order_id' => $order->id]);

    app(DeleteUnpaidOrderAction::class)->execute($order, null);

    expect(Order::query()->count())->toBe(0)
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

test('commandes en paiement échoué ou annulées : supprimables', function (OrderStatus $status) {
    app(DeleteUnpaidOrderAction::class)->execute(unpaidOrder(['status' => $status]), null);

    expect(Order::query()->count())->toBe(0);
})->with([OrderStatus::PaymentFailed, OrderStatus::Cancelled]);

test('commande payée, préparée, expédiée ou remboursée : refusée', function (OrderStatus $status) {
    $order = unpaidOrder(['status' => $status]);

    expect(fn () => app(DeleteUnpaidOrderAction::class)->execute($order, null))->toThrow(OrderDeletionNotAllowed::class);
    expect(Order::query()->count())->toBe(1);
})->with([OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::PickedUp, OrderStatus::Refunded]);

test('commande facturée : refusée même si son statut le permettrait', function () {
    $order = unpaidOrder(['status' => OrderStatus::Cancelled]);
    Invoice::factory()->create(['order_id' => $order->id]);

    expect(fn () => app(DeleteUnpaidOrderAction::class)->execute($order, null))->toThrow(OrderDeletionNotAllowed::class);
    expect(Order::query()->count())->toBe(1);
});

test('carte en attente : refusée tant que la session Stripe peut encore être payée, acceptée ensuite', function () {
    $recent = unpaidOrder(['payment_method' => PaymentMethod::Stripe, 'created_at' => now()->subMinutes(20)]);
    $old = unpaidOrder(['payment_method' => PaymentMethod::Stripe, 'created_at' => now()->subHours(2)]);

    expect(fn () => app(DeleteUnpaidOrderAction::class)->execute($recent, null))->toThrow(OrderDeletionNotAllowed::class, 'réessayez dans');

    app(DeleteUnpaidOrderAction::class)->execute($old, null);
    expect(Order::query()->pluck('id')->all())->toBe([$recent->id]);
});

test('admin : bouton visible seulement sur les commandes non réglées, suppression depuis la liste', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $unpaid = unpaidOrder();
    $paid = unpaidOrder(['status' => OrderStatus::Paid]);

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('deleteUnpaid', $unpaid)
        ->assertTableActionHidden('deleteUnpaid', $paid)
        ->callTableAction('deleteUnpaid', $unpaid)
        ->assertNotified('Commande supprimée.');

    expect(Order::query()->pluck('id')->all())->toBe([$paid->id]);
});

test('admin : suppression depuis la fiche commande, retour à la liste', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $order = unpaidOrder();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('deleteUnpaid')
        ->assertRedirect();

    expect(Order::query()->count())->toBe(0);
});
