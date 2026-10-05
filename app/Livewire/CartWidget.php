<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\CartItem;
use App\Services\Cart\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tiroir panier et page `/panier` (T08, PLAN.md §7). Toute la logique
 * métier est déléguée à `CartService`, jamais réimplémentée ici.
 */
class CartWidget extends Component
{
    public bool $removedNotice = false;

    /** @var list<string> */
    public array $removedNames = [];

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = CartItem::query()->findOrFail($itemId);
        $this->authorizeItem($item);

        app(CartService::class)->updateQuantity($item, $quantity);
    }

    public function removeItem(int $itemId): void
    {
        $item = CartItem::query()->findOrFail($itemId);
        $this->authorizeItem($item);

        app(CartService::class)->remove($item);
    }

    #[On('cart-updated')]
    public function refreshCart(): void {}

    public function render()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->currentCart();

        ['items' => $items, 'removed' => $removed] = $cartService->validItems($cart);

        if ($removed !== []) {
            $this->removedNames = $removed;
            $this->removedNotice = true;
        }

        return view('livewire.cart-widget', [
            'items' => $items,
            'totals' => $cartService->totals($cart),
        ]);
    }

    /**
     * Une ligne de panier n'appartient qu'au panier courant (session ou
     * utilisateur) : jamais d'accès à la ligne d'un autre panier
     * (QUALITE.md §2.4, IDOR).
     */
    private function authorizeItem(CartItem $item): void
    {
        $cart = app(CartService::class)->currentCart();
        abort_unless($item->cart_id === $cart->id, 403);
    }
}
