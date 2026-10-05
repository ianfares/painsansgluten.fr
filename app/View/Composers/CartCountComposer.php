<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Services\Cart\CartService;
use Illuminate\View\View;

/**
 * Nombre d'articles dans le panier, affiché sur le badge du panier de
 * l'en-tête (T08) — présent sur chaque page publique via le layout.
 */
class CartCountComposer
{
    public function __construct(private readonly CartService $cartService) {}

    public function compose(View $view): void
    {
        $cart = $this->cartService->currentCart();
        $view->with('cartCount', $this->cartService->totals($cart)['count']);
    }
}
