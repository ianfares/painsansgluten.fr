<?php

declare(strict_types=1);

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake(); // isole le listener de facturation (T16), testé séparément.
    $this->actingAs(Admin::factory()->create(), 'admin');
});

test('l\'action préparer n\'est visible que pour une commande payée', function () {
    $paid = Order::factory()->create(['status' => OrderStatus::Paid]);
    $pending = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('prepare', $paid)
        ->assertTableActionHidden('prepare', $pending);
});

test('l\'action expédier n\'est visible que pour une commande en préparation', function () {
    $preparing = Order::factory()->create(['status' => OrderStatus::Preparing]);
    $paid = Order::factory()->create(['status' => OrderStatus::Paid]);

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('ship', $preparing)
        ->assertTableActionHidden('ship', $paid);
});

test('expédier une commande sans numéro de suivi est refusé', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Preparing]);

    Livewire::test(ListOrders::class)
        ->callTableAction('ship', $order, data: ['tracking_number' => ''])
        ->assertHasTableActionErrors(['tracking_number' => 'required']);

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});

test('expédier avec un numéro de suivi passe la commande à expédiée', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Preparing]);

    Livewire::test(ListOrders::class)
        ->callTableAction('ship', $order, data: ['tracking_number' => 'XX123456789FR']);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($order->tracking_number)->toBe('XX123456789FR');
});

test('l\'action marquer livrée n\'est visible que pour une commande expédiée', function () {
    $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);
    $preparing = Order::factory()->create(['status' => OrderStatus::Preparing]);

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('deliver', $shipped)
        ->assertTableActionHidden('deliver', $preparing);

    Livewire::test(ListOrders::class)->callTableAction('deliver', $shipped);
    expect($shipped->fresh()->status)->toBe(OrderStatus::Delivered);
});

test('rembourser un virement génère un avoir', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::Paid,
    ]);
    // Facture d'origine requise pour que le listener d'avoir (T16) fonctionne.
    Invoice::factory()->create(['order_id' => $order->id, 'type' => InvoiceType::Invoice]);

    Livewire::test(ListOrders::class)->callTableAction('refund', $order);

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('rembourser un paiement carte est refusé tant que Stripe (T14) n\'est pas intégré', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Stripe,
        'status' => OrderStatus::Paid,
    ]);

    Livewire::test(ListOrders::class)->callTableAction('refund', $order);

    // La commande n'est pas modifiée : aucun faux appel Stripe inventé.
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});
