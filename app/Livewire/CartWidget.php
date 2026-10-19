<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\DeliveryMethod;
use App\Livewire\Concerns\PushesAnalyticsEvents;
use App\Models\CartItem;
use App\Services\Analytics\Ecommerce;
use App\Services\Cart\CartService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tiroir panier et page `/panier` (T08, PLAN.md §7). Toute la logique
 * métier est déléguée à `CartService`, jamais réimplémentée ici.
 */
class CartWidget extends Component
{
    use PushesAnalyticsEvents;

    /** Affiché sur la page /panier (pas dans le tiroir) : pas de lien « Voir mon panier ». */
    #[Locked]
    public bool $onCartPage = false;

    public bool $removedNotice = false;

    /** @var list<string> */
    public array $removedNames = [];

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = CartItem::query()->findOrFail($itemId);
        $this->authorizeItem($item);

        $before = $item->quantity;
        app(CartService::class)->updateQuantity($item, $quantity);
        $this->pushQuantityChange($item, $before, (int) (CartItem::query()->whereKey($item->id)->value('quantity') ?? 0));
    }

    public function removeItem(int $itemId): void
    {
        $item = CartItem::query()->findOrFail($itemId);
        $this->authorizeItem($item);

        $before = $item->quantity;
        app(CartService::class)->remove($item);
        $this->pushQuantityChange($item, $before, 0);
    }

    /** add_to_cart / remove_from_cart selon le sens du changement de quantité. */
    private function pushQuantityChange(CartItem $item, int $before, int $after): void
    {
        if ($after === $before || $item->product === null) {
            return;
        }

        $this->pushAnalytics(
            $after > $before ? 'add_to_cart' : 'remove_from_cart',
            app(Ecommerce::class)->single($item->product, auth()->user(), abs($after - $before)),
        );
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

        $totals = $cartService->totals($cart);

        // Met à jour le compteur de l'en-tête (rendu serveur au chargement).
        $this->dispatch('cart-count', count: $totals['count']);

        return view('livewire.cart-widget', [
            'items' => $items,
            'totals' => $totals,
            'minimumChronopost' => $cartService->minimumOrderFor(DeliveryMethod::ChronopostRelay),
            'minimumPickup' => $cartService->minimumOrderFor(DeliveryMethod::MerchantPickup),
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
