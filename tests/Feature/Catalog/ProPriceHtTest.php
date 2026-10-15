<?php

declare(strict_types=1);

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Admin;
use App\Models\Product;
use App\Models\User;
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
