<?php

declare(strict_types=1);

use App\Livewire\CheckoutWizard;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Seed une grille de port + un calendrier d'expédition valides, pour que
 * `CartService::totals()` ne renvoie jamais `shipping_error` dans les tests
 * qui ne cherchent pas spécifiquement à tester ce blocage (T13, cf.
 * docs/DECISIONS.md).
 */
function seedShippingOk(): void
{
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->save();

    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ttc' => 590]);
}

function fillStep1(Testable $component): Testable
{
    return $component
        ->set('email', 'cliente@example.com')
        ->set('first_name', 'Jeanne')
        ->set('last_name', 'Dupont')
        ->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')
        ->set('billing_postal_code', '50300')
        ->set('billing_city', 'Avranches');
}

function fillStep2(Testable $component): Testable
{
    return $component
        ->set('relay_postal_code', '50300')
        ->set('relay_name', 'Relais Avranches Centre');
}

test('un invité peut finaliser une commande par virement', function () {
    seedShippingOk();
    $product = Product::factory()->create(['price_ttc' => 1000]);
    app(CartService::class)->add($product, 2);

    $component = Livewire::test(CheckoutWizard::class);
    fillStep1($component)->call('goToStep', 2);
    fillStep2($component)->call('goToStep', 3);
    $component->call('goToStep', 4)
        ->set('cgvAccepted', true)
        ->set('paymentMethod', 'bank_transfer')
        ->call('pay')
        ->assertHasNoErrors();

    expect(Order::query()->count())->toBe(1);

    $order = Order::query()->first();
    expect($order->user_id)->toBeNull()
        ->and($order->email)->toBe('cliente@example.com')
        ->and($order->payment_method->value)->toBe('bank_transfer')
        ->and($order->status->value)->toBe('pending_payment')
        ->and($order->relay_name)->toBe('Relais Avranches Centre')
        ->and($order->items)->toHaveCount(1);

    $component->assertRedirect(route('checkout.confirmation', $order->token));
});

test('les coordonnées et l\'adresse d\'un client connecté sont pré-remplies', function () {
    seedShippingOk();
    $user = User::factory()->create(['phone' => '0687654321']);
    Address::factory()->for($user)->create([
        'line1' => '12 avenue des Remparts',
        'postal_code' => '50300',
        'city' => 'Avranches',
    ]);

    $this->actingAs($user);

    Livewire::test(CheckoutWizard::class)
        ->assertSet('email', $user->email)
        ->assertSet('first_name', $user->first_name)
        ->assertSet('last_name', $user->last_name)
        ->assertSet('phone', '0687654321')
        ->assertSet('billing_line1', '12 avenue des Remparts')
        ->assertSet('billing_postal_code', '50300')
        ->assertSet('billing_city', 'Avranches');
});

test('impossible de payer sans accepter les CGV', function () {
    seedShippingOk();
    $product = Product::factory()->create();
    app(CartService::class)->add($product, 1);

    $component = Livewire::test(CheckoutWizard::class);
    fillStep1($component)->call('goToStep', 2);
    fillStep2($component)->call('goToStep', 3);
    $component->call('goToStep', 4)
        ->set('paymentMethod', 'bank_transfer')
        ->call('pay')
        ->assertHasErrors(['cgvAccepted']);

    expect(Order::query()->count())->toBe(0);
});

test('impossible d\'avancer à l\'étape récapitulatif sans indiquer de relais', function () {
    seedShippingOk();
    $product = Product::factory()->create();
    app(CartService::class)->add($product, 1);

    $component = Livewire::test(CheckoutWizard::class);
    fillStep1($component)->call('goToStep', 2);

    $component->call('goToStep', 3)
        ->assertHasErrors(['relay_postal_code', 'relay_name'])
        ->assertSet('step', 2);
});

test('la livraison en Corse n\'est pas disponible', function () {
    seedShippingOk();
    $product = Product::factory()->create();
    app(CartService::class)->add($product, 1);

    $component = Livewire::test(CheckoutWizard::class);
    fillStep1($component)->call('goToStep', 2);

    $component
        ->set('relay_postal_code', '20000')
        ->set('relay_name', 'Relais Ajaccio')
        ->call('goToStep', 3)
        ->assertHasErrors(['relay_postal_code'])
        ->assertSet('step', 2);
});

test('une configuration de livraison incomplète bloque la commande sans la créer', function () {
    // Pas de ShippingRate seedée : CartService::totals() renvoie `shipping_error`.
    $product = Product::factory()->create();
    app(CartService::class)->add($product, 1);

    $component = Livewire::test(CheckoutWizard::class);
    fillStep1($component)->call('goToStep', 2);
    fillStep2($component)->call('goToStep', 3);
    $component->call('goToStep', 4)
        ->set('cgvAccepted', true)
        ->set('paymentMethod', 'bank_transfer')
        ->call('pay');

    expect(Order::query()->count())->toBe(0);
    $component->assertSet('error', 'Configuration incomplète, impossible de finaliser la commande pour le moment. Contactez-nous.');
});

test('un double-clic sur payer ne crée qu\'une seule commande', function () {
    seedShippingOk();
    $product = Product::factory()->create();
    app(CartService::class)->add($product, 1);

    $component = Livewire::test(CheckoutWizard::class);
    fillStep1($component)->call('goToStep', 2);
    fillStep2($component)->call('goToStep', 3);
    $component->call('goToStep', 4)
        ->set('cgvAccepted', true)
        ->set('paymentMethod', 'bank_transfer')
        ->call('pay');

    expect(Order::query()->count())->toBe(1);

    // Second clic (la commande a déjà été créée, `submitting` reste à true
    // après un `pay()` réussi — voir app/Livewire/CheckoutWizard.php::pay()).
    $component->call('pay');

    expect(Order::query()->count())->toBe(1);
});
