<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Settings\HomepageSettings;
use App\Settings\ShopSettings;
use Illuminate\View\View;

/**
 * Page d'accueil (PLAN.md §5.1) : bannière, texte de présentation,
 * catégories, produits mis en avant, infos livraison.
 */
class HomeController extends Controller
{
    public function __invoke(HomepageSettings $homepage, ShopSettings $shop): View
    {
        $featured = Product::query()
            ->published()
            ->whereIn('id', $homepage->featured_product_ids)
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $homepage->featured_product_ids))
            ->values();

        if ($featured->isEmpty()) {
            $featured = Product::query()->published()->where('is_available', true)->inRandomOrder()->limit(4)->get();
        }

        return view('catalog.home', [
            'categories' => Category::query()->where('is_active', true)->orderBy('position')->get(),
            'featured' => $featured,
            'homepageSettings' => $homepage,
            'shopSettings' => $shop,
        ]);
    }
}
