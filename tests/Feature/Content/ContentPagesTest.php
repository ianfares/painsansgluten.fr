<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\Redirect;

test('une page publiée répond 200 et affiche son titre', function () {
    $page = Page::factory()->create(['is_published' => true, 'title' => 'Mentions légales', 'slug' => 'mentions-legales']);

    $response = $this->get(route('content.show', $page));

    $response->assertOk()->assertSee('Mentions légales');
});

test('une page non publiée renvoie 404', function () {
    $page = Page::factory()->create(['is_published' => false, 'slug' => 'brouillon']);

    $this->get(route('content.show', $page))->assertNotFound();
});

test('un slug qui n\'est ni catégorie ni page renvoie 404', function () {
    $this->get('/ceci-n-existe-pas')->assertNotFound();
});

test('un slug de catégorie et un slug de page ne se marchent pas dessus', function () {
    Category::factory()->create(['slug' => 'pains-sans-gluten', 'is_active' => true]);
    $page = Page::factory()->create(['is_published' => true, 'slug' => 'contact', 'title' => 'Contact']);

    $this->get('/pains-sans-gluten')->assertOk();
    $this->get(route('content.show', $page))->assertOk()->assertSee('Contact');
});

test('un slug qui commence par un mot réservé reste résolu par le résolveur générique', function () {
    Page::factory()->create(['is_published' => true, 'slug' => 'faq-livraison', 'title' => 'Livraison FAQ']);
    Page::factory()->create(['is_published' => true, 'slug' => 'commandes-speciales', 'title' => 'Commandes spéciales']);

    $this->get('/faq-livraison')->assertOk()->assertSee('Livraison FAQ');
    $this->get('/commandes-speciales')->assertOk()->assertSee('Commandes spéciales');
    $this->get('/faq')->assertOk();
    $this->get('/panier')->assertOk();
});

test('la page FAQ répond 200, groupe les questions et masque les non publiées', function () {
    FaqItem::factory()->create(['question' => 'Livrez-vous en Corse ?', 'group' => 'Livraison', 'is_published' => true]);
    FaqItem::factory()->create(['question' => 'Question cachée', 'is_published' => false]);

    $response = $this->get(route('faq'));

    $response->assertOk()
        ->assertSee('Livrez-vous en Corse ?')
        ->assertSee('Livraison')
        ->assertDontSee('Question cachée');
});

test('une redirection 301 active fonctionne', function () {
    Redirect::factory()->create(['source' => '/collections/all', 'target' => '/boutique', 'status_code' => 301, 'is_active' => true]);

    $this->get('/collections/all')->assertRedirect('/boutique')->assertStatus(301);
});

test('une redirection désactivée ne redirige plus', function () {
    Redirect::factory()->create(['source' => '/ancienne-page', 'target' => '/boutique', 'is_active' => false]);

    $this->get('/ancienne-page')->assertNotFound();
});

test('la règle générique /products/{slug} redirige vers /produit/{slug}', function () {
    $this->get('/products/pain-nordique?variant=123')->assertRedirect('/produit/pain-nordique')->assertStatus(301);
});
