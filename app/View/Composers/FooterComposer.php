<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Page;
use App\Settings\ShopSettings;
use Illuminate\View\View;

/**
 * Données partagées par le pied de page public (T06) : pages légales
 * publiées, coordonnées boutique.
 */
class FooterComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'shopSettings' => app(ShopSettings::class),
            'footerPages' => Page::query()->where('is_published', true)->orderBy('title')->get(),
        ]);
    }
}
