<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $shipping = app(ShippingSettings::class);
    $shipping->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $shipping->order_cutoff_time = '15:00';
    $shipping->production_lead_days = 1;
    $shipping->save();

    Queue::fake();
    Mail::fake();
    $this->actingAs(Admin::factory()->create(), 'admin');
});

test('la liste des commandes affiche la commande', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$order]);
});

test('l\'onglet par défaut n\'affiche que les vraies commandes, les autres sont dans « Non abouties »', function () {
    $paid = Order::factory()->create(['status' => OrderStatus::Paid]);
    $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);
    $pickedUp = Order::factory()->create(['status' => OrderStatus::PickedUp]);
    $pendingTransfer = Order::factory()->create(['payment_method' => PaymentMethod::BankTransfer, 'status' => OrderStatus::PendingPayment]);
    $pendingManual = Order::factory()->create(['payment_method' => PaymentMethod::Stripe, 'status' => OrderStatus::PendingPayment, 'created_by_admin_id' => Admin::factory()->create()->id]);
    $pendingCard = Order::factory()->create(['payment_method' => PaymentMethod::Stripe, 'status' => OrderStatus::PendingPayment]);
    $failed = Order::factory()->create(['payment_method' => PaymentMethod::Stripe, 'status' => OrderStatus::PaymentFailed]);
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);
    $refunded = Order::factory()->create(['status' => OrderStatus::Refunded]);
    $cancelledTransfer = Order::factory()->create(['payment_method' => PaymentMethod::BankTransfer, 'status' => OrderStatus::Cancelled]);

    $real = [$paid, $shipped, $pickedUp, $pendingTransfer, $pendingManual];
    $other = [$pendingCard, $failed, $cancelled, $refunded, $cancelledTransfer];

    Livewire::test(ListOrders::class)
        ->assertSet('activeTab', 'orders')
        ->assertCanSeeTableRecords($real)
        ->assertCanNotSeeTableRecords($other)
        ->set('activeTab', 'not_completed')
        ->assertCanSeeTableRecords($other)
        ->assertCanNotSeeTableRecords($real)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([...$real, ...$other]);
});

test('le filtre « Virements en attente » fonctionne dans l\'onglet par défaut', function () {
    $pendingTransfer = Order::factory()->create(['payment_method' => PaymentMethod::BankTransfer, 'status' => OrderStatus::PendingPayment]);
    $paid = Order::factory()->create(['status' => OrderStatus::Paid]);

    Livewire::test(ListOrders::class)
        ->filterTable('bank_transfer_pending', true)
        ->assertCanSeeTableRecords([$pendingTransfer])
        ->assertCanNotSeeTableRecords([$paid]);
});

test('l\'action « valider le virement reçu » n\'est visible que pour un virement en attente', function () {
    $pendingBankTransfer = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::PendingPayment,
    ]);
    $pendingStripe = Order::factory()->create([
        'payment_method' => PaymentMethod::Stripe,
        'status' => OrderStatus::PendingPayment,
    ]);
    $paidBankTransfer = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::Paid,
    ]);

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('validateBankTransfer', $pendingBankTransfer)
        ->assertTableActionHidden('validateBankTransfer', $pendingStripe)
        ->assertTableActionHidden('validateBankTransfer', $paidBankTransfer);
});

test('valider un virement depuis le BO passe la commande à payée', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::PendingPayment,
    ]);

    Livewire::test(ListOrders::class)
        ->callTableAction('validateBankTransfer', $order);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('la fiche détail d\'une commande affiche ses lignes et son historique', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create(['product_name' => 'Pain nordique sans gluten']);
    OrderStatusHistory::factory()->for($order)->create(['comment' => 'Commande créée']);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('Pain nordique sans gluten')
        ->assertSee('Commande créée');
});
