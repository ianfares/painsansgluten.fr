<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Settings\ShippingSettings;
use Illuminate\View\View;

/**
 * Fiche produit (PLAN.md §6.3).
 */
class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_unless($product->is_published, 404);

        $related = Product::query()
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_available', true)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return view('catalog.product', [
            'title' => $product->name,
            'product' => $product,
            'related' => $related,
            'shippingSettings' => app(ShippingSettings::class),
        ]);
    }
}
