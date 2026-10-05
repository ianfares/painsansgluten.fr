<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Catalog\BoutiqueController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use Illuminate\View\View;

/**
 * Résolveur d'URL racine (PLAN.md §5, §16.1, §23) : les catégories et les
 * pages de contenu partagent le même niveau d'URL (ex. `/pains-sans-gluten`,
 * `/mentions-legales`), pour matcher les redirections 301 Shopify déjà
 * seedées (T02). On tente catégorie, puis page, sinon 404.
 */
class SlugController extends Controller
{
    public function __construct(private readonly BoutiqueController $boutiqueController) {}

    public function show(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();
        if ($category) {
            return $this->boutiqueController->category($category);
        }

        $page = Page::query()->where('slug', $slug)->where('is_published', true)->first();
        abort_unless($page !== null, 404);

        return view('content.page', ['page' => $page]);
    }
}
