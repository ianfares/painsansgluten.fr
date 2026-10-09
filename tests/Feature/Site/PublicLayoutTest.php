<?php

declare(strict_types=1);

use App\Models\Category;
use App\Settings\HomepageSettings;
use App\Settings\ShopSettings;

test('la page d\'accueil affiche l\'en-tête, le pied de page et le nom de la boutique', function () {
    $shop = app(ShopSettings::class);
    $shop->shop_name = 'Mon Sans Gluten by Angélique';
    $shop->save();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Mon Sans Gluten by Angélique');
    $response->assertSee('Gérer mes préférences');
});

test('le bandeau d\'annonce ne s\'affiche que si activé', function () {
    $homepage = app(HomepageSettings::class);
    $homepage->announcement_active = true;
    $homepage->announcement_text = 'Livraison en point relais partout en France';
    $homepage->save();

    $this->get('/')->assertSee('Livraison en point relais partout en France');

    $homepage->announcement_active = false;
    $homepage->save();

    $this->get('/')->assertDontSee('Livraison en point relais partout en France');
});

test('le méga-menu liste les catégories actives', function () {
    Category::factory()->create(['name' => 'Pains sans gluten', 'is_active' => true]);
    Category::factory()->create(['name' => 'Catégorie désactivée', 'is_active' => false]);

    $response = $this->get('/');

    $response->assertSee('Pains sans gluten');
    $response->assertDontSee('Catégorie désactivée');
});

test('une page inconnue affiche la 404 personnalisée avec des liens de catégories', function () {
    Category::factory()->create(['name' => 'Viennoiseries sans gluten', 'is_active' => true]);

    $response = $this->get('/cette-page-n-existe-pas');

    $response->assertNotFound();
    $response->assertSee('Page introuvable');
    $response->assertSee('Viennoiseries sans gluten');
});

test('le lien Contact de l\'en-tête pointe vers la vraie page de contact', function () {
    $response = $this->get('/');

    $response->assertSee(route('contact'), false);
});

test('la bannière d\'accueil affiche les 3 canaux de vente', function () {
    $response = $this->get('/');

    $response->assertSee('Commande en ligne');
    $response->assertSee('Sur les marchés');
    $response->assertSee('Professionnels');
});

test('le badge "Professionnels" est un lien mailto uniquement si un email de contact est renseigné', function () {
    $shop = app(ShopSettings::class);
    $shop->contact_email = null;
    $shop->save();

    $this->get('/')->assertDontSee('mailto:', false);

    $shop->contact_email = 'contact@painsansgluten.fr';
    $shop->save();

    $this->get('/')->assertSee('mailto:contact@painsansgluten.fr', false);
});
