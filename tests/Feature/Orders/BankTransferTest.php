<?php

declare(strict_types=1);

use App\Actions\Orders\ValidateBankTransferPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\BankTransferValidationNotAllowed;
use App\Listeners\Orders\GenerateInvoiceOnOrderPaid;
use App\Livewire\CheckoutWizard;
use App\Mail\BankTransferCancelledMail;
use App\Mail\BankTransferInstructionsMail;
use App\Mail\BankTransferReminderMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Settings\BankTransferSettings;
use App\Settings\ShippingSettings;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $shipping = app(ShippingSettings::class);
    $shipping->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $shipping->order_cutoff_time = '15:00';
    $shipping->production_lead_days = 1;
    $shipping->free_shipping_enabled = false;
    $shipping->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $bankTransfer = app(BankTransferSettings::class);
    $bankTransfer->account_holder = 'Mon Sans Gluten SARL';
    $bankTransfer->iban = 'FR7630006000011234567890189';
    $bankTransfer->bic = 'AGRIFRPP';
    $bankTransfer->bank_name = 'Crédit Agricole';
    $bankTransfer->cancel_after_days = 5;
    $bankTransfer->auto_cancel_enabled = true;
    $bankTransfer->save();

    // Le listener de facturation (T16, `GenerateInvoiceOnOrderPaid`) est en
    // queue : on l'isole ici, il a sa propre suite de tests dans
    // tests/Feature/Invoicing. `Mail::fake()` (par test) isole les emails.
    Queue::fake();
});

test('une commande par virement envoie l\'email d\'instructions avec la référence', function () {
    Mail::fake();
    $product = Product::factory()->create(['price_ttc' => 1000]);
    app(CartService::class)->add($product, 1);

    Livewire::test(CheckoutWizard::class)
        ->set('email', 'cliente@example.com')
        ->set('first_name', 'Jeanne')
        ->set('last_name', 'Dupont')
        ->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')
        ->set('billing_postal_code', '50300')
        ->set('billing_city', 'Avranches')
        ->call('goToStep', 2)
        ->set('relay_postal_code', '50300')
        ->set('relay_name', 'Relais Avranches Centre')
        ->call('goToStep', 3)
        ->call('goToStep', 4)
        ->set('cgvAccepted', true)
        ->set('paymentMethod', 'bank_transfer')
        ->call('pay');

    $order = Order::query()->sole();

    Mail::assertQueued(BankTransferInstructionsMail::class, fn (BankTransferInstructionsMail $mail): bool => $mail->order->is($order));
});

test('valider un virement passe la commande à payée, recalcule la date d\'expédition et envoie un email', function () {
    Mail::fake();
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::PendingPayment,
        'planned_ship_date' => now()->subMonth()->toDateString(),
    ]);

    $updated = app(ValidateBankTransferPaymentAction::class)->execute($order, adminId: 1);

    expect($updated->status)->toBe(OrderStatus::Paid)
        ->and($updated->paid_at)->not->toBeNull()
        ->and($updated->planned_ship_date->toDateString())->not->toBe(now()->subMonth()->toDateString());

    // L'email « virement reçu » part de la tâche de facturation, une fois la
    // facture émise (lien inclus) : testé dans tests/Feature/Emails (T19).
    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === GenerateInvoiceOnOrderPaid::class);
});

test('impossible de valider un virement sur une commande déjà payée', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::Paid,
    ]);

    expect(fn () => app(ValidateBankTransferPaymentAction::class)->execute($order, adminId: 1))
        ->toThrow(BankTransferValidationNotAllowed::class);
});

test('impossible de valider un virement sur une commande annulée', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::Cancelled,
    ]);

    expect(fn () => app(ValidateBankTransferPaymentAction::class)->execute($order, adminId: 1))
        ->toThrow(BankTransferValidationNotAllowed::class);
});

test('impossible de valider un virement sur une commande payée par carte', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Stripe,
        'status' => OrderStatus::PendingPayment,
    ]);

    expect(fn () => app(ValidateBankTransferPaymentAction::class)->execute($order, adminId: 1))
        ->toThrow(BankTransferValidationNotAllowed::class);
});

test('une relance est envoyée une seule fois à mi-délai', function () {
    Mail::fake();
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::PendingPayment,
        'created_at' => now()->subDays(3), // mi-délai de 5 jours = 2,5 jours
        'bank_transfer_reminder_sent_at' => null,
    ]);

    Artisan::call('orders:process-bank-transfer-deadlines');

    Mail::assertQueued(BankTransferReminderMail::class, 1);
    expect($order->fresh()->bank_transfer_reminder_sent_at)->not->toBeNull();

    Artisan::call('orders:process-bank-transfer-deadlines');

    // Toujours une seule relance au total malgré le second passage.
    Mail::assertQueued(BankTransferReminderMail::class, 1);
});

test('une commande non réglée est annulée automatiquement à échéance', function () {
    Mail::fake();
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::PendingPayment,
        'created_at' => now()->subDays(6), // échéance = 5 jours
    ]);

    Artisan::call('orders:process-bank-transfer-deadlines');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    Mail::assertQueued(BankTransferCancelledMail::class, fn (BankTransferCancelledMail $mail): bool => $mail->order->is($order));
});

test('aucune annulation automatique si l\'option est désactivée', function () {
    Mail::fake();
    $bankTransfer = app(BankTransferSettings::class);
    $bankTransfer->auto_cancel_enabled = false;
    $bankTransfer->save();

    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::BankTransfer,
        'status' => OrderStatus::PendingPayment,
        'created_at' => now()->subDays(6),
    ]);

    Artisan::call('orders:process-bank-transfer-deadlines');

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    Mail::assertNotQueued(BankTransferCancelledMail::class);
});
