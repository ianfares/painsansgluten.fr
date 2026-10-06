<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Services\Cart\CartService;
use App\Settings\HomepageSettings;
use Illuminate\View\View;

/**
 * Données partagées par le layout public de base (T08, T17) : nombre
 * d'articles dans le panier (badge d'en-tête) et favicon (`HomepageSettings`).
 */
class CartCountComposer
{
    public function __construct(private readonly CartService $cartService) {}

    public function compose(View $view): void
    {
        $cart = $this->cartService->currentCart();
        $view->with([
            'cartCount' => $this->cartService->totals($cart)['count'],
            'homepageSettings' => app(HomepageSettings::class),
        ]);
    }
}
