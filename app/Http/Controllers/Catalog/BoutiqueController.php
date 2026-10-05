<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Grille boutique (tous produits) et pages catégories (PLAN.md §5, §6.3).
 */
class BoutiqueController extends Controller
{
    public function index(): View
    {
        return view('catalog.boutique', [
            'title' => 'Boutique sans gluten',
            'products' => Product::query()->published()->with('category')->orderBy('position')->paginate(24),
            'categories' => Category::query()->where('is_active', true)->orderBy('position')->get(),
            'activeCategory' => null,
        ]);
    }

    public function category(Category $category): View
    {
        abort_unless($category->is_active, 404);

        return view('catalog.boutique', [
            'title' => $category->name,
            'products' => $category->products()->published()->with('category')->orderBy('position')->paginate(24),
            'categories' => Category::query()->where('is_active', true)->orderBy('position')->get(),
            'activeCategory' => $category,
        ]);
    }
}
