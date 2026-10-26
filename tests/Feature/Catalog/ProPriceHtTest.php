<?php

declare(strict_types=1);

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Livewire\CartWidget;
use App\Livewire\CheckoutWizard;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->product = Product::factory()->create(['is_published' => true, 'price_ttc' => 1055, 'vat_rate' => 5.5]);
});

test('fiche produit : un compte professionnel voit le prix HT, puis le TTC et le taux de TVA', function () {
    $html = $this->actingAs(User::factory()->pro()->create())
        ->get(route('products.show', $this->product))->assertOk()->getContent();

    expect(preg_replace('/\s+/u', ' ', $html))->toContain('10,00 € <span class="text-sm font-normal text-ink-muted">HT</span>')
        ->toContain('soit 10,55 € TTC (TVA 5,5 %)');
});

test('fiche produit : pro avec remise → HT barré, HT remisé, TTC remisé', function () {
    $pro = User::factory()->pro()->create();
    $pro->forceFill(['discount_percent' => 10])->save();

    $html = preg_replace('/\s+/u', ' ', $this->actingAs($pro)->get(route('products.show', $this->product))->getContent());

    // 10,55 € - 10 % = 9,49 € TTC (arrondi au centime) → 9,00 € HT.
    expect($html)->toContain('<s class="text-[0.8em] font-normal text-ink-muted">10,00 €</s>')
        ->toContain('9,00 € <span class="text-sm font-normal text-ink-muted">HT</span>')
        ->toContain('soit 9,49 € TTC')
        ->toContain('Votre remise -10 %');
});

test('fiche produit : un particulier et un visiteur voient le TTC, jamais le HT', function () {
    foreach ([null, User::factory()->create()] as $user) {
        if ($user) {
            $this->actingAs($user);
        }
        $html = $this->get(route('products.show', $this->product))->getContent();

        expect($html)->toContain('10,55 €')->not->toContain('soit 10,55 € TTC');
    }
});

test('back-office : prix HT dans la liste et calculé dans la fiche produit', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(ListProducts::class)->assertTableColumnStateSet('price_ht', 1000, $this->product);

    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->assertSee('Prix HT (calculé)')
        ->assertSee('10,00 €')
        ->fillForm(['price_ttc' => '21.10', 'vat_rate' => '5.50'])
        ->assertSee('20,00 €');
});

test('boutique : la carte produit affiche le HT à un pro, le TTC à un particulier', function () {
    $this->actingAs(User::factory()->pro()->create());
    expect(preg_replace('/\s+/u', ' ', $this->get(route('boutique.index'))->getContent()))
        ->toContain('10,00 € <span class="text-sm font-normal text-ink-muted">HT</span>');

    $this->actingAs(User::factory()->create());
    expect($this->get(route('boutique.index'))->getContent())->toContain('10,55 €')->not->toContain('soit 10,55 € TTC');
});

test('panier et commande d\'un pro : lignes HT, sous-total HT, port HT, total HT, TVA, total TTC ; même total HT dans le récapitulatif de commande', function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->shipping_vat_rate = 20;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $pro = User::factory()->pro()->create();
    $this->actingAs($pro);
    app(CartService::class)->add($this->product, 2);

    $totals = app(CartService::class)->totals(app(CartService::class)->currentCart());
    // 2 × 10,55 € TTC à 5,5 % = 20,00 € HT ; port 6,00 € TTC à 20 % = 5,00 € HT.
    expect($totals['show_ht'])->toBeTrue()
        ->and($totals['subtotal_ht'])->toBe(2000)
        ->and($totals['shipping_ht'])->toBe(500)
        ->and($totals['total_ht'])->toBe(2500)
        ->and($totals['total_vat'])->toBe(2710 - 2500);

    Livewire::test(CartWidget::class)
        ->assertSee('10,00 € HT / unité')
        ->assertSee('20,00 € HT')
        ->assertSee('Sous-total HT')
        ->assertSee('Frais de port HT')
        ->assertSee('Total HT')
        ->assertSee('25,00 €')
        ->assertSee('Total TTC')
        ->assertSee('27,10 €');

    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(['features' => []])]);
    Livewire::test(CheckoutWizard::class)
        ->set('email', 'pro@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')->set('phone', '0612345678')
        ->set('billing_line1', '1 rue du Pain')->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2)
        ->set('relay_postal_code', '33000')->set('relay_city', 'BORDEAUX')->call('searchRelays')->set('relay_id', '8339S')
        ->call('goToStep', 3)
        ->assertSee('20,00 € HT')
        ->assertSee('Sous-total HT')
        ->assertSee('Total HT')
        ->assertSee('25,00 €')
        ->assertSee('27,10 €');
});

test('panier d\'un particulier : inchangé, en TTC', function () {
    $this->actingAs(User::factory()->create());
    app(CartService::class)->add($this->product, 1);

    Livewire::test(CartWidget::class)
        ->assertSee('10,55 € / unité')
        ->assertSee('Sous-total TTC')
        ->assertDontSee('Total HT');
});
