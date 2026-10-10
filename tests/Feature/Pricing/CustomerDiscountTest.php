<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueInvoiceAction;
use App\Enums\PaymentMethod;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Livewire\CheckoutWizard;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Pricing\CustomerPricing;
use App\Settings\ShippingSettings;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

/** Grille de port valide : 5,00 € HT → 6,00 € TTC (TVA 20 %), port jamais offert. */
function discountShippingOk(): void
{
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->save();

    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);
}

/** Client connecté avec une remise, panier de 2 × 7,50 € (TVA 5,5 %), commande passée par le tunnel. */
function discountedCheckout(int $percent): Order
{
    discountShippingOk();
    $user = User::factory()->create(['email' => 'remise@example.com']);
    $user->forceFill(['discount_percent' => $percent])->save();
    test()->actingAs($user);

    app(CartService::class)->add(Product::factory()->create(['price_ttc' => 750, 'vat_rate' => 5.5, 'shipping_weight_g' => 400]), 2);

    $component = Livewire::test(CheckoutWizard::class)
        ->set('email', 'remise@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2)
        ->set('relay_postal_code', '50300')->set('relay_name', 'Relais Avranches Centre')
        ->call('goToStep', 3)->call('goToStep', 4)
        ->set('cgvAccepted', true)->set('paymentMethod', 'bank_transfer')
        ->call('pay')
        ->assertHasNoErrors();

    return Order::query()->with('items')->sole();
}

test('remise unitaire arrondie au centime le plus proche', function () {
    expect(CustomerPricing::unitDiscount(750, 10))->toBe(75)
        ->and(CustomerPricing::unitDiscount(333, 15))->toBe(50) // 49,95 → 50
        ->and(CustomerPricing::unitDiscount(333, 0))->toBe(0)
        ->and(CustomerPricing::unitNet(750, 10))->toBe(675);
});

test('le taux appliqué dépend du compte : visiteur, particulier, pro en attente ou validé, désactivé, plafond', function () {
    $withDiscount = function (User $user, int $percent): User {
        $user->forceFill(['discount_percent' => $percent])->save();

        return $user;
    };

    expect(CustomerPricing::percentFor(null))->toBe(0)
        ->and(CustomerPricing::percentFor($withDiscount(User::factory()->create(), 10)))->toBe(10)
        ->and(CustomerPricing::percentFor($withDiscount(User::factory()->pro(approved: false)->create(), 10)))->toBe(0)
        ->and(CustomerPricing::percentFor($withDiscount(User::factory()->pro()->create(), 10)))->toBe(10)
        ->and(CustomerPricing::percentFor($withDiscount(User::factory()->deactivated()->create(), 10)))->toBe(0)
        ->and(CustomerPricing::percentFor($withDiscount(User::factory()->create(), 95)))->toBe(CustomerPricing::MAX_PERCENT);
});

test('panier : la remise porte sur les produits, jamais sur les frais de port ; un invité paie le prix public', function () {
    discountShippingOk();
    $product = Product::factory()->create(['price_ttc' => 750, 'shipping_weight_g' => 400]);

    app(CartService::class)->add($product, 2);
    $guest = app(CartService::class)->totals(app(CartService::class)->currentCart());
    expect($guest)->toMatchArray(['subtotal_ttc' => 1500, 'discount_ttc' => 0, 'shipping_ttc' => 600, 'total_ttc' => 2100]);

    $user = User::factory()->create();
    $user->forceFill(['discount_percent' => 10])->save();
    $this->actingAs($user);
    app(CartService::class)->add($product, 2);

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart());
    expect($totals)->toMatchArray(['subtotal_ttc' => 1350, 'discount_percent' => 10, 'discount_ttc' => 150, 'shipping_ttc' => 600, 'total_ttc' => 1950]);
});

test('la commande fige la remise sur chaque ligne et dans les totaux, TVA calculée après remise', function () {
    $order = discountedCheckout(10);
    $item = $order->items->sole();

    expect($order->discount_percent)->toBe(10)
        ->and($order->discount_total_ttc)->toBe(150)
        ->and($order->subtotal_ttc)->toBe(1350)
        ->and($order->shipping_ttc)->toBe(600)
        ->and($order->total_ttc)->toBe(1950)
        // 1350 / 1,055 = 1280 HT ; port 500 HT
        ->and($order->total_ht)->toBe(1280 + 500)
        ->and($order->total_vat)->toBe(1950 - 1780)
        ->and($item->unit_price_ttc)->toBe(750)
        ->and($item->unit_discount_ttc)->toBe(75)
        ->and($item->line_total_ttc)->toBe(1350)
        ->and($item->line_total_ht)->toBe(1280);

    // Changer la remise du compte ensuite ne modifie pas une commande passée.
    $order->user->forceFill(['discount_percent' => 30])->save();
    expect($order->fresh()->total_ttc)->toBe(1950);
});

test('Stripe reçoit le prix unitaire remisé : la somme envoyée égale le total de la commande', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $stripeHttp = new FakeStripeHttpClient(['id' => 'cs_test_remise', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_remise']);
    ApiRequestor::setHttpClient($stripeHttp);

    $order = discountedCheckout(10);
    $order->update(['payment_method' => PaymentMethod::Stripe]);

    $this->get(route('checkout.stripe.start', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_remise');
    $lines = $stripeHttp->requests[0]['params']['line_items'];

    expect($lines[0]['price_data']['unit_amount'])->toBe(675)
        ->and($lines[0]['quantity'])->toBe(2)
        ->and($lines[0]['price_data']['product_data']['name'])->toContain('remise -10 %')
        ->and($lines[1]['price_data']['unit_amount'])->toBe(600)
        ->and(collect($lines)->sum(fn (array $l) => $l['quantity'] * $l['price_data']['unit_amount']))->toBe($order->total_ttc);

    ApiRequestor::setHttpClient(null);
});

test('la facture montre le prix public, la remise et des totaux remise déduite', function () {
    $order = discountedCheckout(10);

    $invoice = app(IssueInvoiceAction::class)->execute($order);

    expect($invoice->snapshot['discount'])->toBe(['percent' => 10, 'total_ttc' => 150])
        ->and($invoice->snapshot['lines'][0]['discount_percent'])->toBe(10)
        ->and($invoice->snapshot['lines'][0]['unit_price_ht'])->toBe(711) // 750 / 1,055
        ->and($invoice->snapshot['lines'][0]['total_ht'])->toBe(1280)
        ->and($invoice->total_ttc)->toBe(1950);

    $html = view('pdf.invoice', ['invoice' => $invoice])->render();
    expect($html)->toContain('Remise client de 10 %')->toContain('-1,50 € TTC');
});

test('catalogue : prix barré et prix remisé pour le client connecté, prix public pour le visiteur', function () {
    $product = Product::factory()->create(['price_ttc' => 750]);
    $url = route('products.show', $product);

    $this->get($url)->assertSee('7,50 €')->assertDontSee('Votre remise');

    $user = User::factory()->create();
    $user->forceFill(['discount_percent' => 10])->save();

    $this->actingAs($user)->get($url)
        ->assertSeeInOrder(['<s', '7,50 €', '</s>', '6,75 €'], false)
        ->assertSee('Votre remise -10 %');
});

test('admin : la remise se règle dans la fiche client, bornée à 90 %', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['discount_percent' => 95])->call('save')->assertHasFormErrors(['discount_percent']);
    expect($user->fresh()->discount_percent)->toBe(0);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['discount_percent' => 15])->call('save')->assertHasNoFormErrors();
    expect($user->fresh()->discount_percent)->toBe(15);
});

test('le taux ne peut pas venir du navigateur : inscription et profil ignorent un champ de remise', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-profile-information.update'), [
        'first_name' => $user->first_name, 'last_name' => $user->last_name, 'phone' => $user->phone,
        'email' => $user->email, 'discount_percent' => 50,
    ]);

    expect($user->fresh()->discount_percent)->toBe(0);
});
