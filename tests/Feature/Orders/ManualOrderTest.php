<?php

declare(strict_types=1);

use App\Actions\Orders\CreateManualOrderAction;
use App\Actions\Orders\SettleOrderByCreditAction;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\ManualOrderNotAllowed;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Mail\BankTransferInstructionsMail;
use App\Mail\ManualOrderPaymentRequestMail;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $this->admin = Admin::factory()->create();
    $this->client = User::factory()->create(['email' => 'pro@example.com']);
    $this->client->forceFill(['discount_percent' => 10])->save();
    $this->bread = Product::factory()->create(['name' => 'Pain', 'price_ttc' => 750, 'vat_rate' => 5.5, 'shipping_weight_g' => 400]);
});

function manualOrder(array $overrides = []): Order
{
    $args = array_merge([
        'adminId' => test()->admin->id,
        'user' => test()->client,
        'lines' => [['product_id' => test()->bread->id, 'quantity' => 2]],
        'deliveryMethod' => DeliveryMethod::ChronopostRelay,
        'delivery' => ['relay_name' => 'Relais Avranches', 'relay_postal_code' => '50300'],
        'billing' => ['line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches'],
        'paymentMethod' => PaymentMethod::Stripe,
    ], $overrides);

    return app(CreateManualOrderAction::class)->execute(...$args);
}

test('commande manuelle : calcul identique au tunnel (remise du compte, port non remisé), tracée, panier du client intact', function () {
    $clientCart = Cart::query()->create(['user_id' => $this->client->id]);
    $clientCart->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 3]);

    $order = manualOrder();

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->user_id)->toBe($this->client->id)
        ->and($order->created_by_admin_id)->toBe($this->admin->id)
        ->and($order->discount_percent)->toBe(10)
        ->and($order->subtotal_ttc)->toBe(1350)
        ->and($order->shipping_ttc)->toBe(600)
        ->and($order->total_ttc)->toBe(1950)
        ->and($order->email)->toBe('pro@example.com')
        ->and($order->relay_name)->toBe('Relais Avranches');

    // Le panier temporaire a disparu, celui du client n'a pas bougé.
    expect(Cart::query()->where('user_id', $this->client->id)->count())->toBe(1)
        ->and((int) $clientCart->items()->sum('quantity'))->toBe(3);
});

test('carte : le client reçoit le lien de paiement ; virement : les instructions habituelles', function () {
    $card = manualOrder();
    Mail::assertQueued(ManualOrderPaymentRequestMail::class, fn (ManualOrderPaymentRequestMail $m) => $m->hasTo('pro@example.com') && $m->order->is($card));
    expect((new ManualOrderPaymentRequestMail($card))->render())->toContain(route('checkout.stripe.start', $card));

    manualOrder(['paymentMethod' => PaymentMethod::BankTransfer]);
    Mail::assertQueued(BankTransferInstructionsMail::class);
});

test('commande manuelle refusée : compte désactivé, labo non autorisé, relais manquant, aucun produit, paiement « avoir » direct', function (array $overrides) {
    if (($overrides['deactivate'] ?? false) === true) {
        $this->client->forceFill(['deactivated_at' => now()])->save();
        unset($overrides['deactivate']);
    }

    expect(fn () => manualOrder($overrides))->toThrow(ManualOrderNotAllowed::class);
    expect(Order::query()->count())->toBe(0)
        ->and(Cart::query()->count())->toBe(0);
})->with([
    'désactivé' => [['deactivate' => true]],
    'labo' => [['deliveryMethod' => DeliveryMethod::LabPickup]],
    'relais' => [['delivery' => ['relay_name' => '', 'relay_postal_code' => '50300']]],
    'Corse' => [['delivery' => ['relay_name' => 'Ajaccio', 'relay_postal_code' => '20000']]],
    'aucun produit' => [['lines' => []]],
    'produit inconnu' => [['lines' => [['product_id' => 999999, 'quantity' => 1]]]],
    'avoir direct' => [['paymentMethod' => PaymentMethod::CreditSettlement]],
]);

test('« Valider sans paiement (avoir) » : payée, moyen « Réglé par avoir », facture avec la référence', function () {
    $order = manualOrder();

    $paid = app(SettleOrderByCreditAction::class)->execute($order, 'Avoir A-2026-00003', $this->admin->id);

    expect($paid->status)->toBe(OrderStatus::Paid)
        ->and($paid->payment_method)->toBe(PaymentMethod::CreditSettlement)
        ->and(Payment::query()->where('order_id', $order->id)->sole()->provider_ref)->toBe('Avoir A-2026-00003');

    $invoice = $order->fresh()->invoices()->sole();
    expect($invoice->snapshot['payment_method'])->toBe('Réglé par avoir')
        ->and($invoice->snapshot['settlement_reference'])->toBe('Avoir A-2026-00003')
        ->and(view('pdf.invoice', ['invoice' => $invoice])->render())->toContain('Réglé par avoir : Avoir A-2026-00003');

    // Déjà payée : refus ; motif vide : refus.
    expect(fn () => app(SettleOrderByCreditAction::class)->execute($order, 'encore', $this->admin->id))->toThrow(ManualOrderNotAllowed::class);
    expect(fn () => app(SettleOrderByCreditAction::class)->execute(manualOrder(), '  ', $this->admin->id))->toThrow(ManualOrderNotAllowed::class);
});

test('admin : page « Nouvelle commande » et action « avoir » visible seulement en attente de paiement', function () {
    $this->actingAs($this->admin, 'admin');

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'user_id' => $this->client->id,
            'billing_line1' => '1 rue du Pain', 'billing_postal_code' => '50300', 'billing_city' => 'Avranches',
            'lines' => [['product_id' => $this->bread->id, 'quantity' => 1]],
            'delivery_method' => DeliveryMethod::ChronopostRelay->value,
            'relay_name' => 'Relais Avranches', 'relay_postal_code' => '50300',
            'payment_method' => PaymentMethod::BankTransfer->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::query()->sole();
    expect($order->total_ttc)->toBe(675 + 600)
        ->and($order->created_by_admin_id)->toBe($this->admin->id);

    $paid = Order::factory()->create(['status' => OrderStatus::Paid]);
    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('settleByCredit', $order)
        ->assertTableActionHidden('settleByCredit', $paid);
});

test('un client ne peut pas accéder à la création de commande', function () {
    $this->actingAs($this->client)->get('/admin/orders/create')->assertRedirect();
});
