<?php

declare(strict_types=1);

/*
 * Non-régression des failles relevées par l'audit de sécurité du 2026-10-09
 * (docs/JOURNAL.md, docs/DECISIONS.md).
 */

use App\Actions\Invoicing\IssueInvoiceAction;
use App\Actions\Orders\CreateOrderAction;
use App\Actions\Orders\RefundOrderAction;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\RefundNotAvailable;
use App\Filament\Pages\ExportInvoicesCsv;
use App\Livewire\CheckoutWizard;
use App\Mail\StripePaymentAnomalyMail;
use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Settings\BankTransferSettings;
use App\Settings\BillingSettings;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

beforeEach(function () {
    $shipping = app(ShippingSettings::class);
    $shipping->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $shipping->order_cutoff_time = '15:00';
    $shipping->production_lead_days = 1;
    $shipping->free_shipping_enabled = false;
    $shipping->shipping_vat_rate = 20.0;
    $shipping->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $shop = app(ShopSettings::class);
    $shop->admin_notification_email = 'admin@example.test';
    $shop->save();
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

/** Un panier valide + un tunnel arrivé à l'étape de paiement, champs modifiables à volonté. */
function auditWizard(array $overrides = []): Testable
{
    $product = Product::factory()->create(['price_ttc' => 1000, 'vat_rate' => 5.5]);
    app(CartService::class)->add($product, 1);

    $component = Livewire::test(CheckoutWizard::class);
    foreach (array_merge([
        'email' => 'cliente@example.com', 'first_name' => 'Jeanne', 'last_name' => 'Dupont', 'phone' => '0612345678',
        'billing_line1' => '1 rue du Pain', 'billing_postal_code' => '50300', 'billing_city' => 'Avranches',
        'relay_postal_code' => '50300', 'relay_name' => 'Relais Avranches', 'cgvAccepted' => true, 'paymentMethod' => 'bank_transfer',
    ], $overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

// --- Tunnel de commande -----------------------------------------------------

test('appeler « payer » directement revalide les coordonnées : pas de commande avec un email invalide', function () {
    auditWizard(['email' => 'pas-un-email'])->call('pay')->assertHasErrors('email')->assertSet('step', 1);

    expect(Order::query()->count())->toBe(0);
});

test('appeler « payer » directement ne permet pas de livrer en Corse', function () {
    auditWizard(['relay_postal_code' => '20000'])->call('pay')->assertHasErrors('relay_postal_code');

    expect(Order::query()->count())->toBe(0);
});

test('l\'étape du tunnel ne peut pas être modifiée depuis le navigateur', function () {
    Livewire::test(CheckoutWizard::class)->set('step', 4);
})->throws(CannotUpdateLockedPropertyException::class);

test('au-delà de 3 commandes en 10 minutes avec le même email, la commande est refusée (anti-abus)', function () {
    Mail::fake();
    foreach (range(1, 3) as $i) {
        auditWizard()->call('pay');
    }

    auditWizard()->call('pay')->assertSet('error', 'Trop de commandes ont été passées avec cette adresse email. Merci de réessayer dans quelques minutes.');

    expect(Order::query()->count())->toBe(3);
});

test('deux validations du même panier ne créent qu\'une seule commande', function () {
    Mail::fake();
    $component = auditWizard();
    $cart = app(CartService::class)->currentCart();

    $component->call('pay');
    expect(fn () => app(CreateOrderAction::class)->execute(
        $cart,
        ['email' => 'a@example.com', 'first_name' => 'A', 'last_name' => 'B', 'phone' => '0612345678'],
        ['first_name' => 'A', 'last_name' => 'B', 'line1' => 'x', 'postal_code' => '50300', 'city' => 'Avranches'],
        ['relay_id' => 'R', 'relay_name' => 'R', 'relay_snapshot' => []],
        PaymentMethod::BankTransfer,
    ))->toThrow(RuntimeException::class);

    expect(Order::query()->count())->toBe(1);
});

// --- Panier -----------------------------------------------------------------

test('un produit supprimé alors qu\'il est dans un panier est retiré sans erreur', function () {
    $product = Product::factory()->create();
    $cartService = app(CartService::class);
    $cartService->add($product, 1);
    $product->delete();

    ['items' => $items] = $cartService->validItems($cartService->currentCart());

    expect($items)->toBeEmpty();
    $this->get(route('panier'))->assertOk();
});

// --- Montants HT / TVA --------------------------------------------------------

test('le HT et la TVA tiennent compte du taux de TVA propre aux frais de port', function () {
    Storage::fake('local');
    $billing = app(BillingSettings::class);
    $billing->company_name = 'Mon Sans Gluten by Angélique';
    $billing->save();

    // 10,00 € TTC à 5,5 % + 6,00 € de port TTC à 20 %
    auditWizard()->call('pay');
    $order = Order::query()->sole();

    expect($order->total_ttc)->toBe(1600)
        ->and($order->total_ht)->toBe(948 + 500)
        ->and($order->total_vat)->toBe(1600 - 1448);

    $invoice = app(IssueInvoiceAction::class)->execute($order);
    $summary = collect($invoice->snapshot['vat_summary'])->keyBy(fn ($row) => (string) $row['rate']);

    expect($summary['5.5']['base_ht'])->toBe(948)
        ->and($summary['20']['base_ht'])->toBe(500)
        ->and($summary['20']['vat'])->toBe(100);
});

// --- Stripe : double paiement -------------------------------------------------

function auditStripeOrder(OrderStatus $status = OrderStatus::PendingPayment): Order
{
    $order = Order::factory()->create(['status' => $status, 'payment_method' => PaymentMethod::Stripe, 'total_ttc' => 1890, 'subtotal_ttc' => 1300, 'shipping_ttc' => 590]);
    OrderItem::factory()->create(['order_id' => $order->id, 'unit_price_ttc' => 650, 'quantity' => 2, 'line_total_ttc' => 1300]);

    return $order;
}

function auditSignedEvent(string $type, array $object, string $id)
{
    config(['services.stripe.webhook_secret' => 'whsec_audit']);
    $payload = json_encode(['id' => $id, 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
    $t = time();

    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_audit'),
    ], $payload);
}

test('relancer le paiement ferme d\'abord la session Stripe précédente', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $http = new FakeStripeHttpClient(['id' => 'cs_new', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_new']);
    ApiRequestor::setHttpClient($http);
    $order = auditStripeOrder();
    Payment::factory()->create(['order_id' => $order->id, 'method' => PaymentMethod::Stripe, 'provider_ref' => 'cs_old', 'amount' => 1890, 'status' => 'pending']);

    $this->get(route('checkout.stripe.start', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_new');

    expect($http->requests[0]['url'])->toEndWith('/v1/checkout/sessions/cs_old/expire')
        ->and(Payment::query()->where('provider_ref', 'cs_old')->value('status'))->toBe('superseded');
});

test('si la session précédente a déjà été payée, aucune nouvelle session n\'est ouverte', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $http = new FakeStripeHttpClient(['id' => 'cs_new', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_new']);
    $http->routes = [
        '/cs_old/expire' => [400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Session is complete']]],
        '/cs_old' => [200, ['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'complete']],
    ];
    ApiRequestor::setHttpClient($http);
    $order = auditStripeOrder();
    Payment::factory()->create(['order_id' => $order->id, 'method' => PaymentMethod::Stripe, 'provider_ref' => 'cs_old', 'amount' => 1890, 'status' => 'pending']);

    $this->get(route('checkout.stripe.start', $order))->assertRedirect(route('checkout.confirmation', $order));

    expect(collect($http->requests)->pluck('url')->filter(fn ($u) => str_ends_with($u, '/v1/checkout/sessions')))->toBeEmpty();
});

test('un second paiement encaissé sur une commande déjà payée est signalé à l\'admin', function () {
    Mail::fake();
    $order = auditStripeOrder(OrderStatus::Paid);
    Payment::factory()->create(['order_id' => $order->id, 'method' => PaymentMethod::Stripe, 'provider_ref' => 'pi_first', 'amount' => 1890, 'status' => 'paid']);
    Payment::factory()->create(['order_id' => $order->id, 'method' => PaymentMethod::Stripe, 'provider_ref' => 'cs_second', 'amount' => 1890, 'status' => 'superseded']);

    auditSignedEvent('checkout.session.completed', [
        'id' => 'cs_second', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 1890, 'currency' => 'eur',
        'payment_intent' => 'pi_second', 'metadata' => ['order_id' => (string) $order->id],
    ], 'evt_double')->assertOk();

    expect($order->fresh()->payment_anomaly)->toBeTrue()
        ->and(Payment::query()->where('provider_ref', 'pi_second')->value('status'))->toBe('paid_unexpected');
    Mail::assertQueued(StripePaymentAnomalyMail::class, fn ($mail) => str_contains($mail->reason, 'paiement en double'));
});

test('l\'expiration d\'une session remplacée par une relance n\'annule pas la commande', function () {
    $order = auditStripeOrder();
    Payment::factory()->create(['order_id' => $order->id, 'method' => PaymentMethod::Stripe, 'provider_ref' => 'cs_old', 'amount' => 1890, 'status' => 'superseded']);

    auditSignedEvent('checkout.session.expired', [
        'id' => 'cs_old', 'object' => 'checkout.session', 'payment_status' => 'unpaid', 'metadata' => ['order_id' => (string) $order->id],
    ], 'evt_expired_old')->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

// --- Remboursement ------------------------------------------------------------

test('une commande déjà remboursée ne déclenche aucun nouvel appel à Stripe', function () {
    $http = new FakeStripeHttpClient(['id' => 're_x', 'object' => 'refund']);
    ApiRequestor::setHttpClient($http);
    $order = auditStripeOrder(OrderStatus::Refunded);

    expect(fn () => app(RefundOrderAction::class)->execute($order, Admin::factory()->create()->id))->toThrow(RefundNotAvailable::class);

    expect($http->requests)->toBe([]);
});

// --- Export comptable ---------------------------------------------------------

test('l\'export comptable neutralise les formules dans le nom du client', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $order = Order::factory()->create(['number' => 'C2026-00099']);
    Invoice::factory()->create([
        'order_id' => $order->id, 'number' => 'F2026-00099', 'issued_at' => '2026-10-05',
        'snapshot' => ['customer' => ['name' => '=HYPERLINK("http://exemple.test","clic")'], 'vat_summary' => []],
    ]);

    $response = Livewire::test(ExportInvoicesCsv::class)
        ->fillForm(['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->call('export');

    $content = base64_decode(data_get($response->effects, 'download.content'));
    expect($content)->toContain("'=HYPERLINK")->not->toContain(';=HYPERLINK');
});

// --- Factures et tâches planifiées -------------------------------------------

test('la base refuse une seconde facture pour la même commande', function () {
    $order = Order::factory()->create();
    Invoice::factory()->create(['order_id' => $order->id, 'type' => InvoiceType::Invoice]);

    expect(fn () => Invoice::factory()->create(['order_id' => $order->id, 'type' => InvoiceType::Invoice]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('une commande impossible à annuler n\'empêche pas le traitement des virements suivants', function () {
    Mail::fake();
    $bank = app(BankTransferSettings::class);
    $bank->auto_cancel_enabled = true;
    $bank->cancel_after_days = 5;
    $bank->save();

    $first = Order::factory()->create(['payment_method' => PaymentMethod::BankTransfer, 'status' => OrderStatus::PendingPayment, 'created_at' => now()->subDays(10)]);
    $second = Order::factory()->create(['payment_method' => PaymentMethod::BankTransfer, 'status' => OrderStatus::PendingPayment, 'created_at' => now()->subDays(10)]);

    // Le premier virement est validé « pendant » le traitement : sa transition vers Annulée échoue.
    Order::retrieved(function (Order $order) use ($first) {
        if ($order->is($first) && $order->status === OrderStatus::PendingPayment) {
            DB::table('orders')->where('id', $first->id)->update(['status' => OrderStatus::Paid->value]);
        }
    });

    $this->artisan('orders:process-bank-transfer-deadlines')->assertSuccessful();

    expect($second->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($first->fresh()->status)->toBe(OrderStatus::Paid);
});

// --- T23 : en-têtes de sécurité ------------------------------------------------

test('toutes les pages envoient les en-têtes de sécurité, sans divulguer la version de PHP', function () {
    foreach (['/', '/connexion', '/admin/login'] as $url) {
        $this->get($url)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy-Report-Only')
            ->assertHeaderMissing('X-Powered-By');
    }
});
