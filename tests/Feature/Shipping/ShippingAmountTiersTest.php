<?php

declare(strict_types=1);

use App\Actions\Orders\CreateOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\Shipping\WeightOutOfRange;
use App\Filament\Pages\ManageShippingSettings;
use App\Livewire\CartWidget;
use App\Models\Admin;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Shipping\ShippingCostCalculator;
use App\Settings\ShippingSettings;
use Livewire\Livewire;

/**
 * Frais de port par palier de montant du panier (06/11/2026, offre de
 * lancement d'Angélique) : moins de 30 € → 9,50 € ; 30 € à 49,99 € → 5,90 € ;
 * dès 50 € → 4,90 € (TTC). Désactivé : grille au poids inchangée.
 */
beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->minimum_order_chronopost_ttc = 0;
    $settings->minimum_order_pickup_ttc = 0;
    $settings->amount_tiers_enabled = true;
    $settings->amount_tiers = [
        ['min_subtotal_ttc' => 0, 'price_ttc' => 950],
        ['min_subtotal_ttc' => 3000, 'price_ttc' => 590],
        ['min_subtotal_ttc' => 5000, 'price_ttc' => 490],
    ];
    $settings->save();
    // Grille au poids : 10,01 € TTC jusqu'à 20 kg (8,34 HT).
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 20000, 'price_ht' => 834]);
});

function tiers(): ShippingCostCalculator
{
    return app(ShippingCostCalculator::class);
}

function tierSettings(array $changes): void
{
    $settings = app(ShippingSettings::class);
    foreach ($changes as $key => $value) {
        $settings->{$key} = $value;
    }
    $settings->save();
}

function tierCart(int $priceTtc, int $quantity = 1, int $weightG = 300): void
{
    app(CartService::class)->add(Product::factory()->create(['price_ttc' => $priceTtc, 'vat_rate' => 5.5, 'shipping_weight_g' => $weightG]), $quantity);
}

test('chaque borne tombe dans le bon palier', function () {
    expect(tiers()->forWeight(500, 0))->toBe(950)
        ->and(tiers()->forWeight(500, 2999))->toBe(950)
        ->and(tiers()->forWeight(500, 3000))->toBe(590)
        ->and(tiers()->forWeight(500, 4999))->toBe(590)
        ->and(tiers()->forWeight(500, 5000))->toBe(490)
        ->and(tiers()->forWeight(500, 50000))->toBe(490);
});

test('le prix ne dépend plus du poids', function () {
    expect(tiers()->forWeight(100, 4000))->toBe(590)
        ->and(tiers()->forWeight(19000, 4000))->toBe(590);
});

test('désactivé : retour à la grille au poids', function () {
    tierSettings(['amount_tiers_enabled' => false]);

    expect(tiers()->forWeight(500, 6000))->toBe(1001)
        ->and(tiers()->nextAmountTier(1000))->toBeNull();
});

test('colis trop lourd : toujours refusé en mode paliers', function () {
    expect(fn () => tiers()->forWeight(20010, 6000))->toThrow(WeightOutOfRange::class);
});

test('le franco de port reste prioritaire', function () {
    tierSettings(['free_shipping_enabled' => true, 'free_shipping_threshold_ttc' => 8000]);

    expect(tiers()->forWeight(500, 8000))->toBe(0)
        ->and(tiers()->forWeight(500, 7999))->toBe(490)
        ->and(tiers()->nextAmountTier(8000))->toBeNull();
});

test('grille saisie dans le désordre : triée avant calcul', function () {
    tierSettings(['amount_tiers' => [
        ['min_subtotal_ttc' => 5000, 'price_ttc' => 490],
        ['min_subtotal_ttc' => 0, 'price_ttc' => 950],
        ['min_subtotal_ttc' => 3000, 'price_ttc' => 590],
    ]]);

    expect(tiers()->forWeight(500, 3500))->toBe(590)
        ->and(tiers()->nextAmountTier(1000))->toBe(['missing_ttc' => 2000, 'price_ttc' => 590]);
});

test('aucun palier ne couvre le montant (pas de palier à 0) : grille au poids', function () {
    tierSettings(['amount_tiers' => [['min_subtotal_ttc' => 3000, 'price_ttc' => 590]]]);

    expect(tiers()->forWeight(500, 1000))->toBe(1001)
        ->and(tiers()->forWeight(500, 3000))->toBe(590);
});

test('palier suivant : montant manquant et prix', function () {
    expect(tiers()->nextAmountTier(1000))->toBe(['missing_ttc' => 2000, 'price_ttc' => 590])
        ->and(tiers()->nextAmountTier(2999))->toBe(['missing_ttc' => 1, 'price_ttc' => 590])
        ->and(tiers()->nextAmountTier(3000))->toBe(['missing_ttc' => 2000, 'price_ttc' => 490])
        ->and(tiers()->nextAmountTier(4950))->toBe(['missing_ttc' => 50, 'price_ttc' => 490])
        ->and(tiers()->nextAmountTier(5000))->toBeNull();
});

test('panier : le palier porte sur le montant après remise, hors port', function () {
    tierCart(1500, 2); // 30,00 €

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart());

    expect($totals['shipping_ttc'])->toBe(590)
        ->and($totals['total_ttc'])->toBe(3590)
        ->and($totals['next_shipping_tier'])->toBe(['missing_ttc' => 2000, 'price_ttc' => 490]);
});

test('retrait : port gratuit et pas de message de palier', function () {
    tierCart(1000);

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart(), DeliveryMethod::MerchantPickup);

    expect($totals['shipping_ttc'])->toBe(0)
        ->and($totals['next_shipping_tier'])->toBeNull();
});

test('commande : le port enregistré est celui du palier', function () {
    tierCart(2500, 2); // 50,00 €

    $order = app(CreateOrderAction::class)->execute(
        app(CartService::class)->currentCart(),
        ['email' => 'palier@example.com', 'first_name' => 'Jeanne', 'last_name' => 'Dupont', 'phone' => '0612345678'],
        ['first_name' => 'Jeanne', 'last_name' => 'Dupont', 'line1' => '1 rue du Pain', 'postal_code' => '50300', 'city' => 'Avranches'],
        ['relay_id' => 'REL-1', 'relay_name' => 'Relais', 'relay_snapshot' => ['name' => 'Relais']],
        PaymentMethod::Stripe,
    );

    expect($order->shipping_ttc)->toBe(490)
        ->and($order->total_ttc)->toBe(5490);
});

test('panier : message « Plus que X € » vers le palier suivant', function () {
    tierCart(2000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSee('Plus que 10,00 € pour bénéficier de la livraison à 5,90 €');

    tierCart(1500);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSee('Plus que 15,00 € pour bénéficier de la livraison à 4,90 €');

    tierCart(1500);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('pour bénéficier de la livraison');
});

test('compte pro : message en TTC, palier calculé sur le montant après remise', function () {
    $pro = User::factory()->pro()->create();
    $pro->discount_percent = 10;
    $pro->save();
    $this->actingAs($pro);
    tierCart(1700, 2); // 34,00 € TTC catalogue, 30,60 € après remise de 10 %

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart());
    expect($totals['subtotal_ttc'])->toBe(3060)
        ->and($totals['shipping_ttc'])->toBe(590)
        ->and($totals['next_shipping_tier'])->toBe(['missing_ttc' => 1940, 'price_ttc' => 490]);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSee('Plus que 19,40 € TTC pour bénéficier de la livraison à 4,90 € TTC');
});

test('compte pro sous 30 € après remise : palier à 9,50 €', function () {
    $pro = User::factory()->pro()->create();
    $pro->discount_percent = 10;
    $pro->save();
    $this->actingAs($pro);
    tierCart(1600, 2); // 32,00 € catalogue, 28,80 € après remise : reste sous 30 €

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart());
    expect($totals['subtotal_ttc'])->toBe(2880)
        ->and($totals['shipping_ttc'])->toBe(950);
});

test('panier : pas de message quand les paliers sont désactivés', function () {
    tierSettings(['amount_tiers_enabled' => false]);
    tierCart(2000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('pour bénéficier de la livraison');
});

test('admin : paliers saisis en euros, stockés en centimes', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ManageShippingSettings::class)
        ->fillForm([
            'amount_tiers_enabled' => true,
            'amount_tiers' => [
                ['min_subtotal_ttc' => '0', 'price_ttc' => '8.90'],
                ['min_subtotal_ttc' => '35', 'price_ttc' => '5.50'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(ShippingSettings::class)->refresh();
    expect($settings->amount_tiers_enabled)->toBeTrue()
        ->and(array_values($settings->amount_tiers))->toBe([
            ['min_subtotal_ttc' => 0, 'price_ttc' => 890],
            ['min_subtotal_ttc' => 3500, 'price_ttc' => 550],
        ]);
});

test('admin : grille refusée sans palier à 0 € ou avec deux paliers au même montant', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ManageShippingSettings::class)
        ->fillForm(['amount_tiers' => [['min_subtotal_ttc' => '30', 'price_ttc' => '5.90']]])
        ->call('save')
        ->assertHasFormErrors(['amount_tiers']);

    Livewire::test(ManageShippingSettings::class)
        ->fillForm(['amount_tiers' => [
            ['min_subtotal_ttc' => '0', 'price_ttc' => '9.50'],
            ['min_subtotal_ttc' => '0', 'price_ttc' => '5.90'],
        ]])
        ->call('save')
        ->assertHasFormErrors(['amount_tiers']);
});

test('panier : prix de la livraison en point relais et total avec livraison', function () {
    tierCart(1008, 2); // 20,16 €

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSeeInOrder(['Sous-total TTC', '20,16 €', 'Livraison en Chronopost Relais', '9,50 €', 'Plus que 9,84 €', 'Total avec livraison en Chronopost Relais', '29,66 €'])
        ->assertDontSee('Total hors livraison');
});

test('panier : livraison offerte au-delà du franco', function () {
    tierSettings(['free_shipping_enabled' => true, 'free_shipping_threshold_ttc' => 10000]);
    tierCart(5000, 2); // 100,00 €

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSeeInOrder(['Livraison en Chronopost Relais', 'Offerte', 'Total avec livraison en Chronopost Relais', '100,00 €'])
        ->assertDontSee('pour bénéficier de la livraison');
});

test('panier : produit en retrait uniquement, pas de prix ni de message point relais', function () {
    tierCart(2000);
    app(CartService::class)->add(Product::factory()->notShippable()->create(['price_ttc' => 500]), 1);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('Livraison en Chronopost Relais')
        ->assertDontSee('pour bénéficier de la livraison')
        ->assertSee('Total hors livraison');
});

test('panier : Chronopost coupé, pas de prix de point relais', function () {
    tierSettings(['chronopost_enabled' => false]);
    tierCart(2000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('Livraison en Chronopost Relais')
        ->assertSee('Livraison Chronopost momentanément suspendue');
});

test('panier : grille désactivée, affichage inchangé (total hors livraison)', function () {
    tierSettings(['amount_tiers_enabled' => false]);
    tierCart(2000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('Livraison en Chronopost Relais')
        ->assertSee('Total hors livraison')
        ->assertSee('Frais de livraison calculés au récapitulatif');
});

test('panier pro : livraison en point relais HT et total TTC', function () {
    $pro = User::factory()->pro()->create();
    $this->actingAs($pro);
    tierCart(1500, 2); // 30,00 € TTC → 5,90 € TTC

    // TVA du port à 20 % (beforeEach) : 5,90 € TTC = 4,92 € HT.
    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSeeInOrder(['Plus que 20,00 € TTC', 'Sous-total HT', 'Livraison en Chronopost Relais HT', '4,92 €', 'Total HT', 'TVA', 'Total TTC', '35,90 €']);
});

test('panier : mention retrait gratuit seulement si un commerçant partenaire est actif', function () {
    tierCart(2000);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('point de retrait partenaire');

    PickupPoint::factory()->create(['is_active' => false, 'latitude' => 48.68, 'longitude' => -1.35]);
    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertDontSee('point de retrait partenaire');

    PickupPoint::factory()->create(['is_active' => true, 'latitude' => 48.68, 'longitude' => -1.35]);
    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSeeInOrder(['Total avec livraison en Chronopost Relais', 'Livraison gratuite en point de retrait partenaire (selon votre adresse).']);
});

test('panier pro : mention retrait gratuit sous le total TTC', function () {
    PickupPoint::factory()->create(['is_active' => true, 'latitude' => 48.68, 'longitude' => -1.35]);
    $this->actingAs(User::factory()->pro()->create());
    tierCart(1500, 2);

    Livewire::test(CartWidget::class, ['onCartPage' => true])
        ->assertSeeInOrder(['Total TTC', 'Livraison gratuite en point de retrait partenaire (selon votre adresse).']);
});
