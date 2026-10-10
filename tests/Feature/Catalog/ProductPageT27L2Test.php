<?php

declare(strict_types=1);

use App\Models\Product;

test('la fiche affiche le prix avec la mention TTC, le poids net et le prix au kilo', function () {
    $product = Product::factory()->create([
        'is_published' => true, 'price_ttc' => 450, 'net_weight_g' => 500,
        'short_description' => null, 'description' => null,
    ]);

    $html = $this->get(route('products.show', $product))->assertOk()->getContent();

    expect($html)->toContain('4,50 €')
        ->toContain('TTC')
        ->toContain('Poids net : 500 g - 9,00 €/kg');
});

test('la fiche affiche le poids en kilos à partir de 1 kg', function () {
    $product = Product::factory()->create([
        'is_published' => true, 'price_ttc' => 1200, 'net_weight_g' => 1200,
        'short_description' => null, 'description' => null,
    ]);

    $this->get(route('products.show', $product))
        ->assertSee('Poids net : 1,2 kg - 10,00 €/kg', false);
});

test('sans poids net, la fiche n\'affiche ni poids ni prix au kilo', function () {
    $product = Product::factory()->create([
        'is_published' => true, 'price_ttc' => 450, 'net_weight_g' => null,
        'short_description' => null, 'description' => null,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertDontSee('Poids net')
        ->assertDontSee('€/kg');
});

test('la description longue se déplie sous la description courte, fermée par défaut', function () {
    $product = Product::factory()->create([
        'is_published' => true, 'short_description' => 'Courte', 'description' => '<p>Longue</p>',
    ]);

    $html = $this->get(route('products.show', $product))->assertOk()->getContent();

    expect($html)->toMatch('/Courte.*<details class="group text-ink">.*<summary.*Lire la suite.*<p>Longue<\/p>/s')
        ->and($html)->not->toContain('<details open class="group text-ink">')
        ->and($html)->not->toContain('<h2>Description</h2>');
});

test('sans description courte, la description longue s\'affiche directement', function () {
    $product = Product::factory()->create([
        'is_published' => true, 'short_description' => null, 'description' => '<p>Longue seule</p>',
    ]);

    $html = $this->get(route('products.show', $product))->assertOk()->getContent();

    expect($html)->toContain('<p>Longue seule</p>')
        ->and($html)->not->toContain('Lire la suite');
});
