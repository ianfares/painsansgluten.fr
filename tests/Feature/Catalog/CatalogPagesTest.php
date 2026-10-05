<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;

test('l\'accueil, la boutique et une page catégorie répondent 200', function () {
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->for($category)->create(['is_published' => true]);

    $this->get(route('home'))->assertOk();
    $this->get(route('boutique.index'))->assertOk();
    $this->get(route('content.show', $category))->assertOk();
});

test('la fiche d\'un produit publié répond 200 et affiche son nom', function () {
    $product = Product::factory()->create(['is_published' => true, 'name' => 'Le Mie\'miam sans gluten']);

    $response = $this->get(route('products.show', $product));

    $response->assertOk();
    $response->assertSee('Le Mie\'miam sans gluten');
});

test('la fiche d\'un produit non publié renvoie 404', function () {
    $product = Product::factory()->create(['is_published' => false]);

    $this->get(route('products.show', $product))->assertNotFound();
});

test('un produit indisponible n\'affiche pas de bouton ajouter', function () {
    $product = Product::factory()->create(['is_published' => true, 'is_available' => false]);

    $response = $this->get(route('products.show', $product));

    $response->assertDontSee('Ajouter au panier');
    $response->assertSee('Indisponible');
});

test('un produit non expédiable affiche son message paramétré au lieu du bouton ajouter', function () {
    $product = Product::factory()->notShippable()->create(['is_published' => true]);

    $response = $this->get(route('products.show', $product));

    $response->assertDontSee('Ajouter au panier');
    $response->assertSee('Disponible uniquement sur nos marchés');
});

test('les blocs vides (conseils, conservation) ne sont pas affichés', function () {
    $product = Product::factory()->create([
        'is_published' => true,
        'tasting_tips' => null,
        'storage' => null,
        'shelf_life' => null,
        'allergen_note' => null,
        'allergens_contains' => [],
        'allergens_traces' => [],
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertDontSee('Conseils de dégustation');
    $response->assertDontSee('Conservation');
    $response->assertDontSee('Allergènes');
});

test('la grille boutique n\'affiche jamais un produit non publié', function () {
    Product::factory()->create(['is_published' => false, 'name' => 'Produit brouillon secret']);

    $this->get(route('boutique.index'))->assertDontSee('Produit brouillon secret');
});
