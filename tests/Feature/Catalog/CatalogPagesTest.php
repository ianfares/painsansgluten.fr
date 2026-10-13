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

test('un produit non expédiable reste commandable et affiche son message « retrait uniquement »', function () {
    $product = Product::factory()->notShippable()->create(['is_published' => true]);

    $response = $this->get(route('products.show', $product));

    $response->assertSee('Ajouter au panier');
    $response->assertSee('disponible uniquement en retrait');
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

test('l\'accueil affiche les 4 engagements, sans « bio » ni certification non confirmée', function () {
    $response = $this->get(route('home'))->assertOk();

    $response->assertSee('Nos engagements')
        ->assertSee('en point relais Chronopost', false)
        ->assertSee('100 % sans gluten', false)
        ->assertSee('Congelez, sortez, savourez', false)
        ->assertSee('fabriqués à la commande', false);

    $section = str($response->getContent())->between('aria-label="Nos engagements"', '</section>');
    expect((string) $section)->not->toContain('bio')->not->toContain('AFDIAG');
});

test('les pages de compte utilisent le gabarit du site (en-tête et pied de page)', function () {
    foreach (['login', 'register', 'password.request'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('auth-card', false)->assertSee('Mon Sans Gluten by Angélique');
    }
});

test('le menu du haut propose « Professionnels » juste après la dernière catégorie (ordinateur et mobile)', function () {
    Category::factory()->create(['name' => 'Biscuits', 'is_active' => true]);

    $html = $this->get('/')->assertOk()->getContent();
    $link = '<a href="'.route('pro.request.create').'"';

    expect(substr_count($html, $link))->toBeGreaterThanOrEqual(2)
        ->and($html)->toMatch('/Biscuits\s*<\/a>\s*<a href="'.preg_quote(route('pro.request.create'), '/').'"[^>]*>\s*<span aria-hidden="true">💼<\/span>\s*Professionnels/');
});

test('texte de présentation mis en forme : gras et listes affichés, script retiré, meta description en texte simple', function () {
    $category = Category::factory()->create([
        'is_active' => true,
        'slug' => 'pains-mis-en-forme',
        'description' => '<p>Nos <strong>pains</strong> du jour</p><ul><li>Campagne</li></ul><script>alert(1)</script>',
    ]);

    expect($category->fresh()->description)->not->toContain('<script>');

    $html = $this->get(route('content.show', $category))->assertOk()->getContent();

    expect($html)->toContain('Nos <strong>pains</strong> du jour')
        ->and($html)->toContain('<li>Campagne</li>')
        ->and($html)->not->toContain('alert(1)')
        ->and($html)->toContain('<meta name="description" content="Nos pains du jour Campagne">');
});

test('un ancien texte de présentation sans mise en forme garde ses retours à la ligne et reste échappé', function () {
    $category = Category::factory()->create([
        'is_active' => true,
        'slug' => 'pains-texte-brut',
        'description' => "Ligne 1 & co\nLigne 2",
    ]);

    expect($category->descriptionHtml())->toBe("Ligne 1 &amp; co<br />\nLigne 2");
});
