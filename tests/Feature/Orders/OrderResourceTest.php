<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Admin;
use App\Models\Order;
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
    $order = Order::factory()->create();

    Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$order]);
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
