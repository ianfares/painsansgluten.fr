<?php

declare(strict_types=1);

use App\Actions\Orders\RefundOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\Orders\OrderRefunded;
use App\Exceptions\Orders\RefundNotAvailable;
use App\Livewire\StripePaymentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $this->stripeHttp = new FakeStripeHttpClient(['id' => 'cs_test_new', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_new']);
    ApiRequestor::setHttpClient($this->stripeHttp);

    // 2 x 7,50 € + 5,90 € de port = 20,90 €
    $this->order = Order::factory()->create([
        'status' => OrderStatus::PendingPayment,
        'payment_method' => PaymentMethod::Stripe,
        'subtotal_ttc' => 1500,
        'shipping_ttc' => 590,
        'total_ttc' => 2090,
    ]);
    OrderItem::factory()->create(['order_id' => $this->order->id, 'unit_price_ttc' => 750, 'quantity' => 2, 'line_total_ttc' => 1500]);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('payer par carte redirige vers Stripe avec les montants de la commande', function () {
    $this->get(route('checkout.stripe.start', $this->order))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_new');

    $params = $this->stripeHttp->requests[0]['params'];
    expect($params['mode'])->toBe('payment')
        ->and($params['locale'])->toBe('fr')
        ->and($params['metadata']['order_id'])->toBe((string) $this->order->id)
        ->and($params['line_items'][0]['price_data']['unit_amount'])->toBe(750)
        ->and($params['line_items'][0]['quantity'])->toBe(2)
        ->and($params['line_items'][1]['price_data']['unit_amount'])->toBe(590)
        ->and($params['success_url'])->toBe(route('checkout.confirmation', $this->order));

    // Rien n'est validé avant le webhook.
    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and(Payment::query()->sole()->provider_ref)->toBe('cs_test_new');
});

test('une commande déjà payée ne repart pas vers Stripe', function () {
    $this->order->update(['status' => OrderStatus::Paid]);

    $this->get(route('checkout.stripe.start', $this->order))
        ->assertRedirect(route('checkout.confirmation', $this->order));

    expect($this->stripeHttp->requests)->toBe([]);
});

test('un paiement refusé peut être relancé', function () {
    $this->order->update(['status' => OrderStatus::PaymentFailed]);

    $this->get(route('checkout.stripe.start', $this->order))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_new');
});

test('si Stripe est indisponible, le client voit un message et la commande reste en attente', function () {
    $this->stripeHttp->status = 500;
    $this->stripeHttp->response = ['error' => ['message' => 'boom', 'type' => 'api_error']];

    $this->get(route('checkout.stripe.start', $this->order))
        ->assertRedirect(route('checkout.confirmation', $this->order))
        ->assertSessionHas('payment_error');

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('la page de confirmation n\'affiche « accepté » que si la commande est payée en base', function () {
    Livewire::test(StripePaymentStatus::class, ['token' => $this->order->token])
        ->assertSee('Paiement en cours de validation')
        ->assertDontSee('bien été accepté');

    $this->order->update(['status' => OrderStatus::Paid]);

    Livewire::test(StripePaymentStatus::class, ['token' => $this->order->token])
        ->assertSee('bien été accepté');
});

test('après 30 s sans confirmation, la page arrête de se rafraîchir et propose de reprendre le paiement', function () {
    $component = Livewire::test(StripePaymentStatus::class, ['token' => $this->order->token]);

    for ($i = 1; $i < StripePaymentStatus::MAX_POLLS; $i++) {
        $component->call('$refresh');
    }

    $component->assertSee('Reprendre le paiement')->assertDontSee('wire:poll', false);
});

test('rembourser une commande carte appelle Stripe avec une clé d\'idempotence', function () {
    Event::fake([OrderRefunded::class]); // l'avoir est testé à part (Invoicing)
    $this->order->update(['status' => OrderStatus::Paid]);
    Payment::factory()->create(['order_id' => $this->order->id, 'method' => PaymentMethod::Stripe, 'provider_ref' => 'pi_test_1', 'amount' => 2090, 'status' => 'paid']);
    $this->stripeHttp->response = ['id' => 're_test_1', 'object' => 'refund', 'status' => 'succeeded'];

    app(RefundOrderAction::class)->execute($this->order, Admin::factory()->create()->id);

    $request = $this->stripeHttp->requests[0];
    expect($request['url'])->toEndWith('/v1/refunds')
        ->and($request['params']['payment_intent'])->toBe('pi_test_1')
        ->and(implode("\n", $request['headers']))->toContain("Idempotency-Key: refund-order-{$this->order->id}")
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('sans paiement Stripe connu, le remboursement est refusé et la commande inchangée', function () {
    $this->order->update(['status' => OrderStatus::Paid]);

    expect(fn () => app(RefundOrderAction::class)->execute($this->order, Admin::factory()->create()->id))
        ->toThrow(RefundNotAvailable::class);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});
