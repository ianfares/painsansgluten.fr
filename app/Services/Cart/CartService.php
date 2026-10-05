<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Exceptions\Shipping\WeightOutOfRange;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\ShippingCostCalculator;
use App\Services\Shipping\ShippingDateCalculator;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Service unique du panier (PLAN.md §7). Toute la logique métier vit ici,
 * hors Livewire, pour rester testable indépendamment de l'UI.
 */
class CartService
{
    public function __construct(
        private readonly ShippingSettings $shippingSettings,
        private readonly ShippingCostCalculator $shippingCostCalculator,
        private readonly ShippingDateCalculator $shippingDateCalculator,
    ) {}

    public function currentCart(): Cart
    {
        if (auth()->check()) {
            return Cart::query()->firstOrCreate(['user_id' => auth()->id()]);
        }

        $sessionId = session()->get('cart_session_id');

        if (! $sessionId) {
            $sessionId = Str::random(40);
            session()->put('cart_session_id', $sessionId);
        }

        return Cart::query()->firstOrCreate(['session_id' => $sessionId, 'user_id' => null]);
    }

    /**
     * Fusionne le panier invité dans celui du client à la connexion
     * (PLAN.md §7). Les quantités des lignes communes s'additionnent,
     * plafonnées à la quantité max par ligne.
     */
    public function mergeIntoUser(User $user): void
    {
        $sessionId = session()->get('cart_session_id');
        if (! $sessionId) {
            return;
        }

        $guestCart = Cart::query()->where('session_id', $sessionId)->whereNull('user_id')->first();
        if (! $guestCart) {
            return;
        }

        $userCart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
        $maxQuantity = $this->shippingSettings->max_quantity_per_line;

        foreach ($guestCart->items as $guestItem) {
            $existing = $userCart->items()->where('product_id', $guestItem->product_id)->first();

            if ($existing) {
                $existing->update(['quantity' => min($maxQuantity, $existing->quantity + $guestItem->quantity)]);
            } else {
                $userCart->items()->create(['product_id' => $guestItem->product_id, 'quantity' => min($maxQuantity, $guestItem->quantity)]);
            }
        }

        $guestCart->delete();
        session()->forget('cart_session_id');
    }

    public function add(Product $product, int $quantity = 1): CartItem
    {
        $cart = $this->currentCart();
        $maxQuantity = $this->shippingSettings->max_quantity_per_line;

        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->update(['quantity' => min($maxQuantity, $item->quantity + $quantity)]);

            return $item->fresh();
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => min($maxQuantity, max(1, $quantity)),
        ]);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        $maxQuantity = $this->shippingSettings->max_quantity_per_line;

        if ($quantity < 1) {
            $item->delete();

            return;
        }

        $item->update(['quantity' => min($maxQuantity, $quantity)]);
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Lignes valides (produit publié, disponible, expédiable). Les lignes
     * devenues invalides sont retirées silencieusement du panier
     * (PLAN.md §7) ; leurs noms sont retournés pour affichage d'un message.
     *
     * @return array{items: Collection<int, CartItem>, removed: list<string>}
     */
    public function validItems(Cart $cart): array
    {
        $removedNames = [];

        $items = $cart->items()->with('product')->get()->filter(function (CartItem $item) use (&$removedNames) {
            $product = $item->product;

            if (! $product->is_published || ! $product->is_available || ! $product->is_shippable) {
                $removedNames[] = $product->name;
                $item->delete();

                return false;
            }

            return true;
        })->values();

        return ['items' => $items, 'removed' => $removedNames];
    }

    /**
     * @return array{count: int, subtotal_ttc: int, shipping_ttc: int|null, total_ttc: int|null,
     *     planned_ship_date: ?CarbonImmutable, shipping_error: ?string}
     */
    public function totals(Cart $cart): array
    {
        ['items' => $items] = $this->validItems($cart);

        $subtotal = $items->sum(fn (CartItem $item) => $item->product->price_ttc * $item->quantity);
        $weight = $items->sum(fn (CartItem $item) => $item->product->shipping_weight_g * $item->quantity);
        $count = $items->sum('quantity');

        $shipping = null;
        $shippingError = null;
        $plannedShipDate = null;

        if ($count > 0) {
            try {
                $shipping = $this->shippingCostCalculator->forWeight($weight, $subtotal);
                $plannedShipDate = $this->shippingDateCalculator->forInstant(CarbonImmutable::now());
            } catch (ShippingNotConfigured|WeightOutOfRange $e) {
                $shippingError = $e->getMessage();
            }
        }

        return [
            'count' => $count,
            'subtotal_ttc' => $subtotal,
            'shipping_ttc' => $shipping,
            'total_ttc' => $shipping !== null ? $subtotal + $shipping : null,
            'planned_ship_date' => $plannedShipDate,
            'shipping_error' => $shippingError,
        ];
    }
}
