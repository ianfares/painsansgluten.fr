<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Category;
use App\Settings\HomepageSettings;
use App\Settings\ShopSettings;
use Illuminate\View\View;

/**
 * Données partagées par l'en-tête public (T06) : catégories actives pour
 * le méga-menu, paramètres boutique/accueil. Logique hors de la vue
 * (QUALITE.md §4.2 : pas de requête dans une vue Blade).
 */
class HeaderComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'homepageSettings' => app(HomepageSettings::class),
            'shopSettings' => app(ShopSettings::class),
            'headerCategories' => Category::query()->where('is_active', true)->orderBy('position')->get(),
        ]);
    }
}
