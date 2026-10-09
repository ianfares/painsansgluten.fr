<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\Product;
use App\Services\Seo\StructuredData;
use App\Settings\ShopSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Fichiers lus par les moteurs et les assistants IA (T21, PLAN.md §17) :
 * robots.txt, sitemap.xml, llms.txt. Générés à la demande depuis la base et
 * mis en cache ; le cache est vidé à chaque modification d'un produit, d'une
 * catégorie, d'une page ou de la FAQ (AppServiceProvider).
 */
class SeoFilesController extends Controller
{
    public const CACHE_KEYS = ['seo.sitemap', 'seo.llms'];

    /** Robots d'IA explicitement autorisés (recherche et réponses des assistants). */
    private const AI_BOTS = [
        'GPTBot', 'OAI-SearchBot', 'ChatGPT-User',
        'ClaudeBot', 'Claude-SearchBot', 'Claude-User',
        'PerplexityBot', 'Perplexity-User',
        'Google-Extended', 'Applebot-Extended', 'Bingbot',
    ];

    public function robots(): Response
    {
        // Hors production (local, préprod) : rien ne doit être indexé.
        if (! app()->environment('production')) {
            return $this->text("User-agent: *\nDisallow: /\n");
        }

        $private = ['/panier', '/commande', '/mon-compte', '/'.config('admin.path'), '/connexion', '/inscription', '/forgot-password', '/reset-password', '/factures'];
        $rules = collect($private)->map(fn ($path) => "Disallow: {$path}")->implode("\n")."\nAllow: /\n";

        $content = "User-agent: *\n{$rules}\n"
            ."# Assistants et moteurs IA : explicitement autorisés\n"
            .collect(self::AI_BOTS)->map(fn ($bot) => "User-agent: {$bot}")->implode("\n")."\n{$rules}\n"
            .'Sitemap: '.route('seo.sitemap')."\n";

        return $this->text($content);
    }

    public function sitemap(): Response
    {
        $xml = Cache::rememberForever('seo.sitemap', function () {
            $urls = collect([
                ['loc' => route('home'), 'lastmod' => null],
                ['loc' => route('boutique.index'), 'lastmod' => null],
                ['loc' => route('faq'), 'lastmod' => FaqItem::query()->max('updated_at')],
                ['loc' => route('contact'), 'lastmod' => null],
            ])
                ->merge(Category::query()->where('is_active', true)->orderBy('position')->get()
                    ->map(fn (Category $c) => ['loc' => route('content.show', $c), 'lastmod' => $c->updated_at]))
                ->merge(Product::query()->published()->orderBy('position')->get()
                    ->map(fn (Product $p) => ['loc' => route('products.show', $p), 'lastmod' => $p->updated_at]))
                ->merge(Page::query()->where('is_published', true)->where('slug', '!=', 'contact')->get()
                    ->map(fn (Page $p) => ['loc' => route('content.show', $p), 'lastmod' => $p->updated_at]));

            return view('seo.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llms(ShopSettings $shop, StructuredData $seo): Response
    {
        $content = Cache::rememberForever('seo.llms', fn () => view('seo.llms', [
            'shop' => $shop,
            'seo' => $seo,
            'categories' => Category::query()->where('is_active', true)->orderBy('position')->get(),
            'products' => Product::query()->published()->with('category')->orderBy('position')->get(),
            'pages' => Page::query()->where('is_published', true)->orderBy('title')->get(),
            'faqCount' => FaqItem::query()->where('is_published', true)->count(),
        ])->render());

        return $this->text($content);
    }

    private function text(string $content): Response
    {
        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
