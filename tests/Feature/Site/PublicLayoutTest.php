<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Settings\HomepageSettings;
use App\Settings\ShopSettings;

test('la page d\'accueil affiche l\'en-tête, le pied de page et le nom de la boutique', function () {
    $shop = app(ShopSettings::class);
    $shop->shop_name = 'Mon Sans Gluten by Angélique';
    $shop->save();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Mon Sans Gluten by Angélique');
    // T22 : le bouton de préférences cookies n'existe que si le bandeau est actif
    // (production + GTM) ; voir tests/Feature/Consent/CookieConsentTest.php.
    $response->assertDontSee('Gérer mes préférences');
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

test('le lien Contact du pied de page pointe vers la vraie page de contact', function () {
    $html = $this->get('/')->getContent();

    expect(substr($html, strpos($html, '<footer')))->toContain(route('contact'));
});

test('l\'en-tête ne contient plus FAQ, Contact ni les pages « Notre histoire » / « Où nous trouver » ; le pied de page les garde', function () {
    Page::query()->create(['slug' => 'notre-histoire', 'title' => 'Notre histoire', 'content' => '<p>x</p>', 'is_published' => true]);
    Page::query()->create(['slug' => 'ou-nous-trouver', 'title' => 'Où nous trouver', 'content' => '<p>x</p>', 'is_published' => true]);

    $html = $this->get('/')->getContent();
    $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
    $footer = substr($html, strpos($html, '<footer'));

    expect($header)->not->toContain(route('faq'))
        ->not->toContain(route('contact'))
        ->not->toContain(route('content.show', 'notre-histoire'))
        ->not->toContain(route('content.show', 'ou-nous-trouver'))
        ->and($footer)->toContain(route('faq'))
        ->toContain(route('contact'))
        ->toContain(route('content.show', 'notre-histoire'))
        ->toContain(route('content.show', 'ou-nous-trouver'));
});

test('l\'étiquette de la bannière est réglable : texte affiché, vide = masquée', function () {
    $homepage = app(HomepageSettings::class);
    expect($homepage->banner_badge_text)->toBe('Création à venir');

    $this->get('/')->assertSee('Création à venir')->assertDontSee('Expédié frais');

    $homepage->banner_badge_text = 'Nouveauté';
    $homepage->save();
    $this->get('/')->assertSee('Nouveauté');

    $homepage->banner_badge_text = '';
    $homepage->save();
    $this->get('/')->assertDontSee('Création à venir')->assertDontSee('Nouveauté');
});

test('la bannière n\'affiche plus le badge « fait main à Avranches » sous le titre', function () {
    $this->get('/')->assertDontSee('fait main à Avranches');
});

test('la bannière d\'accueil affiche les 3 canaux de vente', function () {
    $response = $this->get('/');

    $response->assertSee('Commande en ligne');
    $response->assertSee('Sur les marchés');
    $response->assertSee('Professionnels');
});

test('aucun lien mailto sur l\'accueil : contact par formulaire, Professionnels vers la page dédiée', function () {
    $shop = app(ShopSettings::class);
    $shop->contact_email = 'contact@painsansgluten.fr';
    $shop->save();

    $this->get('/')->assertDontSee('mailto:', false);
});

test('les 5 catégories de l\'accueil tiennent sur une seule ligne à partir de la tablette', function () {
    Category::factory()->count(5)->create(['is_active' => true]);

    $this->get('/')->assertOk()->assertSee('md:grid-cols-5', false);
});
