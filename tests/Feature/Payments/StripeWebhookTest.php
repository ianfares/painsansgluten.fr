<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderRefunded;
use App\Mail\StripePaymentAnomalyMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

const WEBHOOK_SECRET = 'whsec_test_secret';

beforeEach(function () {
    config(['services.stripe.webhook_secret' => WEBHOOK_SECRET]);

    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->save();

    $this->order = Order::factory()->create([
        'status' => OrderStatus::PendingPayment,
        'payment_method' => PaymentMethod::Stripe,
        'total_ttc' => 2590,
        'planned_ship_date' => null,
    ]);
    Payment::factory()->create([
        'order_id' => $this->order->id,
        'method' => PaymentMethod::Stripe,
        'provider_ref' => 'cs_test_1',
        'amount' => 2590,
        'status' => 'pending',
    ]);
});

/**
 * Envoie un événement signé comme le ferait Stripe (en-tête Stripe-Signature).
 *
 * @param  array<string, mixed>  $object
 */
function sendStripeEvent(string $type, array $object, string $id = 'evt_1', ?string $secret = WEBHOOK_SECRET)
{
    $payload = json_encode(['id' => $id, 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) $secret);

    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload);
}

/** @return array<string, mixed> */
function paidSession(Order $order, array $overrides = []): array
{
    return array_merge([
        'id' => 'cs_test_1',
        'object' => 'checkout.session',
        'payment_status' => 'paid',
        'amount_total' => $order->total_ttc,
        'currency' => 'eur',
        'payment_intent' => 'pi_test_1',
        'client_reference_id' => (string) $order->id,
        'metadata' => ['order_id' => (string) $order->id, 'order_number' => $order->number],
    ], $overrides);
}

test('un message non signé par Stripe est refusé (400) et ne change rien', function () {
    sendStripeEvent('checkout.session.completed', paidSession($this->order), secret: 'whsec_pirate')->assertStatus(400);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and(StripeEvent::query()->count())->toBe(0);
});

test('tant que le secret du webhook est la valeur factice, tout message est refusé', function (string $secret) {
    config(['services.stripe.webhook_secret' => $secret]);

    // Même correctement signé avec cette valeur (publique dans .env.example).
    sendStripeEvent('checkout.session.completed', paidSession($this->order), secret: $secret)->assertStatus(503);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
})->with(['whsec_changeme', '']);

test('un paiement confirmé passe la commande en payée et fige la date d\'expédition', function () {
    Event::fake([OrderPaid::class]);

    sendStripeEvent('checkout.session.completed', paidSession($this->order))->assertOk();

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->planned_ship_date)->not->toBeNull()
        ->and($order->payments()->sole()->status)->toBe('paid')
        ->and($order->payments()->sole()->provider_ref)->toBe('pi_test_1');
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('le même événement reçu deux fois n\'est traité qu\'une fois', function () {
    Event::fake([OrderPaid::class]);

    sendStripeEvent('checkout.session.completed', paidSession($this->order))->assertOk();
    sendStripeEvent('checkout.session.completed', paidSession($this->order))->assertOk();

    expect(StripeEvent::query()->count())->toBe(1)
        ->and($this->order->fresh()->statusHistories()->count())->toBe(1);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('un second paiement sur une commande déjà payée est ignoré', function () {
    Event::fake([OrderPaid::class]);

    sendStripeEvent('checkout.session.completed', paidSession($this->order), 'evt_1')->assertOk();
    sendStripeEvent('checkout.session.async_payment_succeeded', paidSession($this->order), 'evt_2')->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('un montant différent laisse le statut inchangé, signale l\'anomalie et prévient l\'admin', function () {
    Mail::fake();
    $shop = app(ShopSettings::class);
    $shop->admin_notification_email = 'admin@example.test';
    $shop->save();

    sendStripeEvent('checkout.session.completed', paidSession($this->order, ['amount_total' => 100]))->assertOk();

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payment_anomaly)->toBeTrue();
    Mail::assertQueued(StripePaymentAnomalyMail::class, fn ($mail) => $mail->hasTo('admin@example.test'));
});

test('une session terminée mais pas encore payée (moyen asynchrone) ne valide rien', function () {
    sendStripeEvent('checkout.session.completed', paidSession($this->order, ['payment_status' => 'unpaid']))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('un paiement asynchrone refusé passe la commande en paiement échoué', function () {
    sendStripeEvent('checkout.session.async_payment_failed', paidSession($this->order, ['payment_status' => 'unpaid']))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::PaymentFailed);
});

test('une session expirée annule la commande en attente', function () {
    sendStripeEvent('checkout.session.expired', paidSession($this->order, ['payment_status' => 'unpaid']))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('l\'expiration d\'une ancienne session n\'annule pas un paiement relancé depuis', function () {
    Payment::factory()->create([
        'order_id' => $this->order->id,
        'method' => PaymentMethod::Stripe,
        'provider_ref' => 'cs_test_2',
        'amount' => 2590,
        'status' => 'pending',
    ]);

    sendStripeEvent('checkout.session.expired', paidSession($this->order, ['payment_status' => 'unpaid']))->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('un remboursement total fait depuis le tableau de bord Stripe passe la commande en remboursée', function () {
    Event::fake([OrderRefunded::class]);
    sendStripeEvent('checkout.session.completed', paidSession($this->order), 'evt_1')->assertOk();

    sendStripeEvent('charge.refunded', [
        'id' => 'ch_test_1',
        'object' => 'charge',
        'payment_intent' => 'pi_test_1',
        'amount' => 2590,
        'amount_refunded' => 2590,
    ], 'evt_2')->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Refunded);
    Event::assertDispatchedTimes(OrderRefunded::class, 1);
});

test('un remboursement partiel est ignoré (V1 : total uniquement)', function () {
    sendStripeEvent('checkout.session.completed', paidSession($this->order), 'evt_1')->assertOk();

    sendStripeEvent('charge.refunded', [
        'id' => 'ch_test_1',
        'object' => 'charge',
        'payment_intent' => 'pi_test_1',
        'amount' => 2590,
        'amount_refunded' => 500,
    ], 'evt_2')->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('un paiement est enregistré même si les réglages d\'expédition sont incomplets', function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = [];
    $settings->save();

    sendStripeEvent('checkout.session.completed', paidSession($this->order))->assertOk();

    $order = $this->order->fresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->planned_ship_date)->toBeNull();
});
