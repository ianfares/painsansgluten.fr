<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\FaqItem;
use App\Models\Product;
use App\Settings\HomepageSettings;
use App\Settings\ShopSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Données structurées schema.org (JSON-LD) lues par Google et les assistants
 * IA (T21, PLAN.md §17). Construites uniquement à partir des données saisies
 * en BO : un champ vide est omis, jamais inventé (CLAUDE.md §3.1).
 */
class StructuredData
{
    public function __construct(
        private readonly ShopSettings $shop,
        private readonly HomepageSettings $homepage,
    ) {}

    /**
     * La marque (Organization), sur l'accueil. Pas de LocalBusiness/Bakery ni
     * d'horaires : le laboratoire ne reçoit pas de public (T26 A8).
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        $sameAs = array_values(array_filter([$this->shop->facebook_url, $this->shop->instagram_url, $this->shop->google_business_url]));

        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $this->shop->address_line1,
            'postalCode' => $this->shop->postal_code,
            'addressLocality' => $this->shop->city,
            'addressCountry' => 'FR',
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => url('/').'#organisation',
            'name' => $this->shopName(),
            'url' => url('/'),
            'logo' => $this->logoUrl(),
            'slogan' => $this->homepage->slogan,
            'description' => 'Boulangerie artisanale 100 % sans gluten à Avranches (Manche) : pains, viennoiseries, pâtisseries et biscuits fabriqués à la commande, livrés en point relais Chronopost en France métropolitaine (hors Corse).',
            'email' => $this->shop->contact_email,
            'telephone' => $this->shop->contact_phone,
            'address' => count($address) > 2 ? $address : null,
            'sameAs' => $sameAs ?: null,
            'areaServed' => ['@type' => 'Country', 'name' => 'France métropolitaine (hors Corse)'],
        ]);
    }

    /**
     * Fiche produit (Product + Offer). L'offre n'est publiée que si un prix existe.
     *
     * @return array<string, mixed>
     */
    public function product(Product $product): array
    {
        $images = collect([$product->getFirstMedia('main')])->merge($product->getMedia('gallery'))
            ->filter()
            ->map(fn ($media) => $media->getUrl('fiche'))
            ->values()
            ->all();

        $offer = $product->price_ttc > 0 ? [
            '@type' => 'Offer',
            'url' => route('products.show', $product),
            'price' => number_format($product->price_ttc / 100, 2, '.', ''),
            'priceCurrency' => 'EUR',
            'availability' => $product->isOrderable() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'seller' => ['@id' => url('/').'#organisation'],
        ] : null;

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'url' => route('products.show', $product),
            'sku' => $product->reference,
            'description' => $this->plainText($product->short_description ?: $product->getAttribute('description')),
            'image' => $images ?: null,
            'category' => $product->category?->name,
            'brand' => ['@type' => 'Brand', 'name' => $this->shopName()],
            'offers' => $offer,
        ]);
    }

    /**
     * Fil d'Ariane. Chaque élément : ['label' => …, 'url' => …].
     *
     * @param  list<array{label: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect([['label' => 'Accueil', 'url' => route('home')], ...$items])
                ->values()
                ->map(fn (array $item, int $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['label'],
                    'item' => $item['url'],
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, FaqItem>  $items
     * @return array<string, mixed>
     */
    public function faq(Collection $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $this->plainText($item->getAttribute('answer'))],
            ])->values()->all(),
        ];
    }

    public function plainText(?string $html, int $limit = 0): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $limit > 0 ? Str::limit($text, $limit, '…') : $text;
    }

    private function shopName(): string
    {
        return $this->shop->shop_name ?: 'Mon Sans Gluten by Angélique';
    }

    private function logoUrl(): ?string
    {
        return $this->homepage->logo_path ? Storage::disk('public')->url($this->homepage->logo_path) : null;
    }
}
