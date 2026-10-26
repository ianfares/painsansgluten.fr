<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSlugRedirect;
use App\Settings\ShippingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fiche produit (PLAN.md §6.3).
 */
class ProductController extends Controller
{
    public function show(Request $request, Product $product): View|RedirectResponse
    {
        abort_unless($product->is_published, 404);

        // La base compare sans tenir compte de la casse : /produit/La-Brioche trouve
        // « la-brioche ». Une seule adresse officielle (SEO) : 301 vers le slug exact.
        if ($request->route()?->originalParameter('product') !== $product->slug) {
            return redirect()->route('products.show', $product, 301);
        }

        $related = Product::query()
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_available', true)
            ->with(['category', 'media'])
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

    /**
     * Ancien slug d'un produit → 301 vers son adresse actuelle ; sinon 404.
     */
    public static function redirectFromOldSlug(string $slug): RedirectResponse
    {
        $redirect = ProductSlugRedirect::query()->with('product')->where('old_slug', $slug)->first();
        abort_unless($redirect?->product !== null && $redirect->product->is_published, 404);

        return redirect()->route('products.show', $redirect->product, 301);
    }
}
