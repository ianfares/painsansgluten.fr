<?php

declare(strict_types=1);

use App\Actions\Orders\CreateManualOrderAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\ManualOrderNotAllowed;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Livewire\CheckoutWizard;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);
});

/** Tunnel rempli jusqu'à l'étape 4 (paiement). */
function transferCheckout(): Testable
{
    app(CartService::class)->add(Product::factory()->create(['price_ttc' => 1000]), 1);

    return Livewire::test(CheckoutWizard::class)
        ->set('email', 'client@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2)
        ->set('relay_postal_code', '50300')->set('relay_name', 'Relais Avranches')
        ->call('goToStep', 3)->call('goToStep', 4);
}

test('virement refusé (option masquée + refus serveur) : invité, particulier, pro en attente, pro dont le virement est désactivé', function (string $who) {
    $user = match ($who) {
        'invité' => null,
        'particulier' => User::factory()->create(),
        'pro en attente' => User::factory()->pro(approved: false)->create(),
        'pro virement désactivé' => User::factory()->pro()->create(['bank_transfer_allowed' => false]),
    };
    if ($user) {
        $this->actingAs($user);
    }

    transferCheckout()
        ->assertSee('Carte bancaire (Stripe)')
        ->assertDontSee('Virement bancaire')
        ->set('cgvAccepted', true)->set('paymentMethod', 'bank_transfer') // falsification depuis le navigateur
        ->call('pay')
        ->assertHasErrors('paymentMethod');

    expect(Order::query()->count())->toBe(0);
})->with(['invité', 'particulier', 'pro en attente', 'pro virement désactivé']);

test('un pro validé voit le virement (par défaut) et peut commander ainsi', function () {
    $pro = User::factory()->pro()->create();
    $this->actingAs($pro);

    transferCheckout()
        ->assertSee('Virement bancaire')
        ->set('cgvAccepted', true)->set('paymentMethod', 'bank_transfer')
        ->call('pay')
        ->assertHasNoErrors();

    expect(Order::query()->sole())
        ->payment_method->toBe(PaymentMethod::BankTransfer)
        ->user_id->toBe($pro->id);
});

test('la création de commande refuse elle-même le virement d\'un particulier (pas seulement le tunnel)', function () {
    $user = User::factory()->create();
    $cartService = app(CartService::class);
    $this->actingAs($user);
    $cartService->add(Product::factory()->create(), 1);

    expect(fn () => app(CreateOrderAction::class)->execute(
        cart: $cartService->currentCart(),
        customer: ['email' => 'a@example.com', 'first_name' => 'A', 'last_name' => 'B', 'phone' => '0612345678'],
        billing: ['line1' => '1 rue', 'postal_code' => '50300', 'city' => 'Avranches'],
        relay: ['relay_id' => 'X', 'relay_name' => 'Relais', 'relay_snapshot' => ['name' => 'Relais', 'postal_code' => '50300']],
        paymentMethod: PaymentMethod::BankTransfer,
        userId: $user->id,
    ))->toThrow(RuntimeException::class, 'réservé aux comptes professionnels');

    expect(Order::query()->count())->toBe(0);
});

test('back-office : le virement se désactive dans la fiche du client pro, et la commande manuelle par virement est alors refusée', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');
    $pro = User::factory()->pro()->create();
    expect($pro->fresh()->bank_transfer_allowed)->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $pro->getRouteKey()])
        ->assertFormFieldExists('bank_transfer_allowed')
        ->fillForm(['bank_transfer_allowed' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pro->fresh()->bank_transfer_allowed)->toBeFalse()
        ->and($pro->fresh()->canPayByBankTransfer())->toBeFalse();

    expect(fn () => app(CreateManualOrderAction::class)->execute(
        adminId: $admin->id,
        user: $pro->fresh(),
        lines: [['product_id' => Product::factory()->create()->id, 'quantity' => 1]],
        deliveryMethod: DeliveryMethod::ChronopostRelay,
        delivery: ['relay_name' => 'Relais Avranches', 'relay_postal_code' => '50300'],
        billing: ['line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches'],
        paymentMethod: PaymentMethod::BankTransfer,
    ))->toThrow(ManualOrderNotAllowed::class);
});
