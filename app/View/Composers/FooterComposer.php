<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Page;
use App\Settings\ShopSettings;
use Illuminate\View\View;

/**
 * Données partagées par le pied de page public (T06) : pages légales
 * publiées, coordonnées boutique, logo (T17, apport logo client).
 */
class FooterComposer
{
    public const LEGAL_SLUGS = ['mentions-legales', 'cgv', 'livraison', 'politique-de-remboursement', 'politique-de-confidentialite'];

    public function compose(View $view): void
    {
        $pages = Page::query()->where('is_published', true)->orderBy('title')->get();

        $view->with([
            'shopSettings' => app(ShopSettings::class),
            // Pages publiées réparties en deux colonnes : légales / autres (Contact a son lien dédié).
            'legalPages' => $pages->filter(fn (Page $page) => in_array($page->slug, self::LEGAL_SLUGS, true))
                ->sortBy(fn (Page $page) => array_search($page->slug, self::LEGAL_SLUGS, true))->values(),
            'footerPages' => $pages->reject(fn (Page $page) => in_array($page->slug, [...self::LEGAL_SLUGS, 'contact'], true))->values(),
        ]);
    }
}
