<?php

declare(strict_types=1);

use App\Models\FaqItem;
use App\Models\Page;
use App\Models\Product;
use App\Settings\ShopSettings;
use Database\Seeders\T26ContentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

// --- A2 : bouton unique ------------------------------------------------------

test('le composant bouton utilise la classe unique .btn (ocre au survol, définie une seule fois)', function () {
    $html = (string) $this->blade('<x-ui.button>Payer</x-ui.button><x-ui.button variant="outline" href="/x">Retour</x-ui.button>');

    expect($html)->toContain('class="btn"')->toContain('class="btn btn-outline"');

    $css = (string) file_get_contents(resource_path('css/app.css'));
    expect($css)->toMatch('/\.btn:hover,\s*\.btn:focus-visible[^{]*\{[^}]*var\(--color-ochre\)/');
});

test('aucune vue ne redéfinit une couleur de bouton en dur', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($file) => str_contains($file->getPathname(), '/vendor/') || str_contains($file->getPathname(), '/emails/') || str_contains($file->getPathname(), '/pdf/'))
        ->filter(fn ($file) => preg_match('/<button[^>]*class="[^"]*\bbg-(sage|ochre)\b/s', $file->getContents()))
        ->map(fn ($file) => $file->getFilename())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

// --- A3 : icônes de l'en-tête ------------------------------------------------

test('l\'en-tête affiche des icônes SVG au trait, sans emoji, avec des libellés accessibles', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('aria-label="Mon compte"')
        ->toContain('aria-label="Mon panier, 0 article"')
        ->not->toContain('👤')
        ->not->toContain('🛍️');
});

// --- A4/A5 : fiche produit -----------------------------------------------------

test('fiche produit : accordéons fermés sauf Ingrédients, bloc Expédition après Conservation, sans badge de livraison', function () {
    $product = Product::factory()->create([
        'is_published' => true, 'is_shippable' => true,
        'ingredients' => '<p>Farine de riz</p>', 'storage' => 'Au sec', 'tasting_tips' => 'Toasté',
    ]);

    $html = $this->get(route('products.show', $product))->assertOk()->getContent();

    expect(substr_count($html, '<details open'))->toBe(1)
        ->and($html)->toMatch('/<details open[^>]*>\s*<summary[^>]*>\s*<h2>Ingrédients<\/h2>/')
        ->and(strpos($html, '<h2>Expédition et livraison</h2>'))->toBeGreaterThan(strpos($html, '<h2>Conservation</h2>'))
        ->and($html)->toContain('retirer votre colis le jour même')
        ->toContain('Livraison en point relais Chronopost, en France métropolitaine (hors Corse).')
        ->not->toContain('Livraison possible');
});

test('pied de page : pages légales et pages de la boutique dans deux colonnes distinctes', function () {
    Page::query()->create(['slug' => 'cgv', 'title' => 'Conditions générales de vente', 'content' => '<p>x</p>', 'is_published' => true]);
    Page::query()->create(['slug' => 'notre-histoire', 'title' => 'Notre histoire', 'content' => '<p>x</p>', 'is_published' => true]);

    $html = $this->get('/')->getContent();
    $footer = substr($html, strpos($html, '<footer'));
    $legal = substr($footer, strpos($footer, 'Informations légales'));
    $shop = substr($footer, strpos($footer, 'La boutique'), strpos($footer, 'Informations légales') - strpos($footer, 'La boutique'));

    expect($legal)->toContain('Conditions générales de vente')
        ->and($shop)->toContain('Notre histoire')
        ->not->toContain('Conditions générales de vente');
});

// --- A8 : coordonnées ---------------------------------------------------------

test('sans téléphone renseigné, aucun téléphone n\'apparaît (pied de page, contact, JSON-LD)', function () {
    $shop = app(ShopSettings::class);
    $shop->contact_phone = null;
    $shop->address_line1 = '71 bis rue du Commandant Bindel';
    $shop->postal_code = '50300';
    $shop->city = 'Avranches';
    $shop->save();

    foreach (['/', route('contact')] as $url) {
        expect($this->get($url)->getContent())->not->toContain('📞')
            ->not->toContain('"telephone"')
            ->toContain('Laboratoire — pas d&#039;accueil du public');
    }
});

test('le téléphone s\'affiche automatiquement dès qu\'il est renseigné', function () {
    $shop = app(ShopSettings::class);
    $shop->contact_phone = '02 33 00 00 00';
    $shop->save();

    expect($this->get('/')->getContent())->toContain('02 33 00 00 00');
});

// --- A8/A9 : pages ------------------------------------------------------------

test('« Notre histoire » est publiée et au menu ; « Où nous trouver » non publiée : 404, hors menu et hors sitemap', function () {
    $this->seed(T26ContentSeeder::class);

    $home = $this->get('/')->getContent();
    expect($home)->toContain(route('content.show', 'notre-histoire'))
        ->not->toContain(route('content.show', 'ou-nous-trouver'))
        ->toContain('Le gluten s&#039;efface, le goût reste.');

    $this->get(route('content.show', 'notre-histoire'))->assertOk()->assertSee('je suis Angélique, boulangère de métier', false);
    $this->get(route('content.show', 'ou-nous-trouver'))->assertNotFound();
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('ou-nous-trouver');
});

test('le seeder de contenus ne publie ni la FAQ ni les textes légaux, et n\'écrase rien', function () {
    Page::query()->create(['slug' => 'cgv', 'title' => 'CGV', 'content' => '<p>Texte saisi en BO</p>', 'is_published' => true]);
    Page::query()->create(['slug' => 'livraison', 'title' => 'Livraison', 'content' => 'Page en cours de rédaction.', 'is_published' => false]);

    $this->seed(T26ContentSeeder::class);
    $this->seed(T26ContentSeeder::class);

    $livraison = Page::query()->where('slug', 'livraison')->firstOrFail();

    expect(Page::query()->where('slug', 'cgv')->value('content'))->toContain('Texte saisi en BO')
        ->and($livraison->content)->toContain('Chronopost')
        ->and($livraison->is_published)->toBeFalse()
        ->and(FaqItem::query()->count())->toBeGreaterThan(10)
        ->and(FaqItem::query()->where('is_published', true)->count())->toBe(0)
        ->and(Page::query()->where('slug', 'notre-histoire')->count())->toBe(1);
});

test('pied de page : contact uniquement par formulaire, sans adresse email', function () {
    $shop = app(ShopSettings::class);
    $shop->contact_email = 'contact@example.test';
    $shop->save();

    $html = $this->get('/')->getContent();
    $footer = substr($html, strpos($html, '<footer'));

    expect($footer)->toContain(route('contact'))->not->toContain('contact@example.test');
});

test('l\'accueil renvoie « Professionnels » vers le formulaire de demande de compte pro', function () {
    expect($this->get('/')->getContent())->toContain('href="'.route('pro.request.create').'"');
});

test('FAQ et Contact sont dans le menu principal ; le lien Facebook apparaît dans le pied de page', function () {
    $shop = app(ShopSettings::class);
    $shop->facebook_url = 'https://www.facebook.com/profile.php?id=61594767200246';
    $shop->save();

    $html = $this->get('/')->getContent();
    $nav = substr($html, strpos($html, route('boutique.index').'"'), 3000);

    expect($nav)->toContain('href="'.route('faq').'"')->toContain('href="'.route('contact').'"')
        ->and($html)->not->toContain('F.A.Q.')
        ->toContain('href="https://www.facebook.com/profile.php?id=61594767200246"');
});

test('« Sur les marchés » mène à « Où nous trouver » seulement une fois la page publiée', function () {
    $page = Page::query()->create(['slug' => 'ou-nous-trouver', 'title' => 'Où nous trouver', 'content' => '<p>x</p>', 'is_published' => false]);
    expect($this->get('/')->getContent())->not->toContain('href="'.route('content.show', $page).'"');

    $page->update(['is_published' => true]);
    expect($this->get('/')->getContent())->toContain('href="'.route('content.show', $page).'"');
});

test('fiche produit : toutes les photos s\'ouvrent en grand (visionneuse), sans jamais servir l\'original', function () {
    Storage::fake('public');
    $product = Product::factory()->create(['is_published' => true]);
    $product->addMedia(UploadedFile::fake()->image('principale.jpg', 1200, 1200))->toMediaCollection('main');
    $product->addMedia(UploadedFile::fake()->image('vue-1.jpg', 1200, 1200))->toMediaCollection('gallery');
    $product->addMedia(UploadedFile::fake()->image('vue-2.jpg', 1200, 1200))->toMediaCollection('gallery');

    $html = $this->get(route('products.show', $product))->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="Voir la photo'))->toBe(3)
        ->and($html)->toContain('aria-label="Agrandir la photo"')
        ->toContain('role="dialog" aria-modal="true" aria-label="Photos du produit"')
        ->not->toContain('principale.jpg"')
        ->toContain('non traitées par l');
});

test('pied de page : pas de logo, icône Facebook sous le lien du formulaire de contact', function () {
    $shop = app(ShopSettings::class);
    $shop->facebook_url = 'https://www.facebook.com/profile.php?id=61594767200246';
    $shop->save();

    $html = $this->get('/')->getContent();
    $footer = substr($html, strpos($html, '<footer'));

    expect($footer)->not->toContain('<img')
        ->and(strpos($footer, 'facebook.com'))->toBeGreaterThan(strpos($footer, 'Formulaire de contact'));
});
