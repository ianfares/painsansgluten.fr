<?php

declare(strict_types=1);

use App\Livewire\CheckoutWizard;
use App\Models\Category;
use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Pas de requête par produit sur les listes (audit du 26/10/2026) : le nombre
 * de requêtes de la page boutique ne dépend pas du nombre de produits.
 */
function boutiqueQueryCount(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get('/boutique')->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('le nombre de requêtes de la boutique ne grandit pas avec le nombre de produits', function () {
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->count(2)->create(['category_id' => $category->id, 'is_published' => true]);
    boutiqueQueryCount(); // 1er affichage : réglages chargés une fois puis mis en cache
    $withTwo = boutiqueQueryCount();

    Product::factory()->count(10)->create(['category_id' => $category->id, 'is_published' => true]);
    $withTwelve = boutiqueQueryCount();

    expect($withTwelve)->toBe($withTwo);
});

test('accessibilité : bouton d\'ajout nommé avec le produit, champs en erreur signalés', function () {
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->create(['category_id' => $category->id, 'is_published' => true, 'name' => 'Pain Nordique']);

    $this->get('/boutique')->assertOk()
        ->assertSee('aria-label="Ajouter au panier : Pain Nordique"', false)
        ->assertSee('aria-controls="mobile-menu"', false)
        ->assertSee('x-trap.noscroll="open"', false);

    app(CartService::class)->add(Product::query()->firstOrFail(), 1);
    Livewire::test(CheckoutWizard::class)
        ->set('email', 'pas-un-email')->call('goToStep', 2)
        ->assertSee('aria-invalid="true" aria-describedby="email-error"', false)
        ->assertSee('id="email-error"', false);
});
