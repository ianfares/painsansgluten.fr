<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductSlugRedirect;
use Illuminate\Support\Facades\Artisan;

test('changer le slug d\'un produit redirige l\'ancienne adresse en 301', function () {
    $product = Product::factory()->create(['slug' => 'brioche-ancienne', 'is_published' => true]);

    $product->update(['slug' => 'brioche-tressee']);

    $this->get('/produit/brioche-ancienne')->assertStatus(301)->assertRedirect(route('products.show', 'brioche-tressee'));
    $this->get('/produit/brioche-tressee')->assertOk();
});

test('une adresse dont seule la casse diffère est redirigée en 301 vers l\'adresse officielle', function () {
    Product::factory()->create(['slug' => 'la-brioche', 'is_published' => true]);

    $this->get('/produit/La-Brioche')->assertStatus(301)->assertRedirect(route('products.show', 'la-brioche'));
});

test('une adresse inconnue reste en 404, une redirection vers un produit non publié aussi', function () {
    $hidden = Product::factory()->create(['slug' => 'secret', 'is_published' => false]);
    $hidden->update(['slug' => 'secret-2']);

    $this->get('/produit/nexiste-pas')->assertNotFound();
    $this->get('/produit/secret')->assertNotFound();
});

test('un slug réutilisé par un autre produit n\'est plus une redirection', function () {
    $old = Product::factory()->create(['slug' => 'pain-du-jour', 'is_published' => true]);
    $old->update(['slug' => 'pain-du-jour-ancien']);

    Product::factory()->create(['slug' => 'pain-du-jour', 'is_published' => true]);

    expect(ProductSlugRedirect::query()->where('old_slug', 'pain-du-jour')->exists())->toBeFalse();
    $this->get('/produit/pain-du-jour')->assertOk();
});

test('la commande de normalisation simule par défaut puis corrige avec --apply', function () {
    $a = Product::factory()->create(['slug' => 'Panettone-artisanal-sans-gluten ', 'is_published' => true]);
    $b = Product::factory()->create(['slug' => 'La-BriÔche-tressée', 'is_published' => true]);

    Artisan::call('catalog:normalize-slugs');
    expect($a->fresh()->slug)->toBe('Panettone-artisanal-sans-gluten ');

    Artisan::call('catalog:normalize-slugs', ['--apply' => true]);
    expect($a->fresh()->slug)->toBe('panettone-artisanal-sans-gluten')
        ->and($b->fresh()->slug)->toBe('la-brioche-tressee');

    $this->get('/produit/'.rawurlencode('La-BriÔche-tressée'))->assertStatus(301)->assertRedirect(route('products.show', 'la-brioche-tressee'));
});
