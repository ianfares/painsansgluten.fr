<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\FaqItem;
use App\Models\Product;
use App\Settings\ShopSettings;

/** Extrait les blocs JSON-LD d'une page, indexés par @type. */
function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return collect($m[1])->map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR))
        ->keyBy(fn ($block) => is_array($block['@type']) ? implode('+', $block['@type']) : $block['@type'])
        ->all();
}

// --- robots.txt ----------------------------------------------------------------

test('hors production, robots.txt interdit tout', function () {
    $this->get('/robots.txt')->assertOk()->assertSee("User-agent: *\nDisallow: /", false);
});

test('en production, robots.txt autorise le site, les robots d\'IA et annonce le sitemap', function () {
    app()['env'] = 'production';

    $content = $this->get('/robots.txt')->assertOk()->getContent();

    expect($content)->toContain('Disallow: /panier')
        ->toContain('Disallow: /mon-compte')
        ->toContain('User-agent: GPTBot')
        ->toContain('User-agent: ClaudeBot')
        ->toContain('User-agent: PerplexityBot')
        ->toContain('Sitemap: '.route('seo.sitemap'))
        ->not->toContain("Disallow: /\n");
});

test('hors production, chaque réponse porte X-Robots-Tag noindex', function () {
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

// --- sitemap.xml ---------------------------------------------------------------

test('le sitemap est un XML valide qui liste les produits publiés et pas les autres', function () {
    $published = Product::factory()->create(['is_published' => true, 'slug' => 'pain-publie']);
    $draft = Product::factory()->create(['is_published' => false, 'slug' => 'pain-brouillon']);

    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain(route('products.show', $published))
        ->not->toContain('pain-brouillon');
});

test('le sitemap se met à jour dès qu\'un produit est publié', function () {
    $this->get('/sitemap.xml');
    $product = Product::factory()->create(['is_published' => false, 'slug' => 'nouveau-pain']);
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('nouveau-pain');

    $product->update(['is_published' => true]);

    expect($this->get('/sitemap.xml')->getContent())->toContain('nouveau-pain');
});

// --- llms.txt ------------------------------------------------------------------

test('llms.txt résume la boutique avec ses catégories et produits publiés', function () {
    $category = Category::factory()->create(['name' => 'Pains', 'is_active' => true, 'description' => 'Nos pains au levain.']);
    Product::factory()->create(['category_id' => $category->id, 'name' => 'Pain Nordique', 'is_published' => true]);
    Product::factory()->create(['category_id' => $category->id, 'name' => "L'Insolent cookie", 'is_published' => true]);
    Product::factory()->create(['category_id' => $category->id, 'name' => 'Pain secret', 'is_published' => false]);

    $this->get('/llms.txt')->assertOk()
        ->assertSee('100 % sans gluten', false)
        ->assertSee('[Pains]('.route('content.show', $category).'): Nos pains au levain.', false)
        ->assertSee('Pain Nordique')
        ->assertSee("[L'Insolent cookie]", false)
        ->assertDontSee('Pain secret');
});

// --- Balises et données structurées --------------------------------------------

test('l\'accueil décrit la marque (Organization, sans horaires) sans inventer les informations absentes', function () {
    $shop = app(ShopSettings::class);
    $shop->contact_phone = null;
    $shop->save();

    $html = $this->get('/')->assertOk()->getContent();
    $organization = jsonLd($html)['Organization'];

    expect($organization['slogan'])->toBe('Le gluten s\'efface, le goût reste.')
        ->and($organization)->not->toHaveKey('telephone')
        ->not->toHaveKey('openingHoursSpecification')
        ->and($html)->not->toContain('LocalBusiness')
        ->not->toContain('"Bakery"')
        ->toContain('<meta name="description"')
        ->toContain('<link rel="canonical"');
});

test('la fiche produit expose Product + Offer (prix, disponibilité) et le fil d\'Ariane', function () {
    $product = Product::factory()->create(['is_published' => true, 'is_available' => true, 'price_ttc' => 650, 'reference' => 'B150']);

    $blocks = jsonLd($this->get(route('products.show', $product))->assertOk()->getContent());

    expect($blocks['Product']['sku'])->toBe('B150')
        ->and($blocks['Product']['offers'])->toMatchArray(['price' => '6.50', 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock'])
        ->and($blocks['BreadcrumbList']['itemListElement'])->toHaveCount(3);
});

test('sans prix, la fiche produit ne publie aucune offre (jamais de prix inventé)', function () {
    $product = Product::factory()->create(['is_published' => true, 'price_ttc' => 0]);

    $blocks = jsonLd($this->get(route('products.show', $product))->getContent());

    expect($blocks['Product'])->not->toHaveKey('offers');
});

test('la FAQ expose FAQPage, et une réponse piégée ne peut pas sortir du bloc JSON', function () {
    FaqItem::factory()->create(['question' => 'Livrez-vous en Corse ?', 'answer' => '<p>Non</p></script><script>alert(1)</script>', 'is_published' => true]);

    $html = $this->get(route('faq'))->getContent();

    expect(jsonLd($html)['FAQPage']['mainEntity'][0]['name'])->toBe('Livrez-vous en Corse ?')
        ->and($html)->not->toContain('<script>alert(1)</script>');
});

test('les pages privées ne sont jamais indexées', function () {
    foreach (['/panier', '/connexion', '/commande'] as $url) {
        expect($this->get($url)->getContent())->toContain('<meta name="robots" content="noindex, nofollow">');
    }

    expect($this->get('/boutique')->getContent())->not->toContain('noindex');
});
