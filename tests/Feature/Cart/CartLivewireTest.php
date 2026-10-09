<?php

declare(strict_types=1);

use App\Livewire\AddToCartButton;
use App\Livewire\CartWidget;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Livewire\Livewire;

beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);
});

test('cliquer sur ajouter au panier ajoute le produit et met à jour le bouton', function () {
    $product = Product::factory()->create(['is_published' => true, 'is_available' => true, 'is_shippable' => true]);

    Livewire::test(AddToCartButton::class, ['product' => $product])
        ->call('add')
        ->assertSet('added', true);

    expect($product->fresh()->orderItems()->count())->toBe(0); // pas de commande, juste le panier
});

test('un produit non commandable ne peut pas être ajouté (403)', function () {
    $product = Product::factory()->create(['is_published' => true, 'is_available' => false]);

    Livewire::test(AddToCartButton::class, ['product' => $product])
        ->call('add')
        ->assertStatus(403);
});

test('le tiroir panier affiche les articles et leur total', function () {
    $product = Product::factory()->create(['price_ttc' => 500]);
    app(CartService::class)->add($product, 2);

    Livewire::test(CartWidget::class)
        ->assertSee($product->name)
        ->assertSee('10,00'); // 2 x 5,00 €
});

test('le tiroir panier envoie le nombre d\'articles au compteur de l\'en-tête', function () {
    $product = Product::factory()->create(['price_ttc' => 500]);
    app(CartService::class)->add($product, 3);

    Livewire::test(CartWidget::class)
        ->assertDispatched('cart-count', count: 3);
});

test('Alpine n\'est pas importé une seconde fois (Livewire le fournit déjà)', function () {
    expect(file_get_contents(resource_path('js/app.js')))->not->toContain('import Alpine');
});

test('paramètres d\'expédition incomplets : le client voit un message simple, jamais le détail technique', function () {
    $settings = app(ShippingSettings::class);
    $settings->production_lead_days = null;
    $settings->save();

    $product = Product::factory()->create(['is_published' => true, 'is_available' => true, 'is_shippable' => true]);
    app(CartService::class)->add($product, 1);

    Livewire::test(CartWidget::class)
        ->assertSee('Livraison momentanément indisponible')
        ->assertDontSee('paramètres de date d');
});
