<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\PaymentMethod;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Consent\CookieConsent;
use App\Services\Pricing\CustomerPricing;

/**
 * Données e-commerce GA4 poussées dans le `dataLayer` (T22, PLAN.md §18) :
 * view_item_list, view_item, add_to_cart, remove_from_cart, view_cart,
 * begin_checkout, add_shipping_info, add_payment_info, purchase.
 *
 * Rien n'est produit quand GTM est inactif (local, tests, préprod sans l'option).
 * GTM ne se charge qu'après consentement : sans accord, les données restent
 * dans le tableau `dataLayer` de la page et ne partent nulle part.
 * Montants : centimes en interne, euros uniquement dans ce format de sortie.
 */
class Ecommerce
{
    public function __construct(private readonly CookieConsent $consent) {}

    public function enabled(): bool
    {
        return $this->consent->isActive();
    }

    /** @return array<string, mixed> */
    public function productItem(Product $product, ?User $user, int $quantity = 1): array
    {
        return $this->item($product, CustomerPricing::unitNet($product->price_ttc, CustomerPricing::percentFor($user)), $quantity);
    }

    /**
     * @param  iterable<Product>  $products
     * @return array<string, mixed>
     */
    public function productList(iterable $products, ?User $user, string $listName): array
    {
        $items = [];
        foreach ($products as $index => $product) {
            $items[] = $this->productItem($product, $user) + ['index' => $index, 'item_list_name' => $listName];
        }

        return ['item_list_name' => $listName, 'items' => $items];
    }

    /**
     * @param  iterable<CartItem>  $cartItems
     * @return array<string, mixed>
     */
    public function cart(iterable $cartItems, ?User $user): array
    {
        $items = [];
        foreach ($cartItems as $cartItem) {
            $items[] = $this->productItem($cartItem->product, $user, $cartItem->quantity);
        }

        return $this->withValue($items);
    }

    /** @return array<string, mixed> */
    public function single(Product $product, ?User $user, int $quantity): array
    {
        return $this->withValue([$this->productItem($product, $user, $quantity)]);
    }

    /**
     * `purchase` : une seule fois par commande (drapeau `ga_purchase_sent` posé
     * de façon atomique), uniquement pour une commande carte payée en BDD.
     * Virement : pas de `purchase` en V1 (paiement confirmé hors navigateur).
     *
     * @return array<string, mixed>|null
     */
    public function claimPurchase(Order $order): ?array
    {
        if (! $this->enabled() || $order->payment_method !== PaymentMethod::Stripe || ! $order->status->isPaidState()) {
            return null;
        }

        $claimed = Order::query()->whereKey($order->id)->where('ga_purchase_sent', false)->update(['ga_purchase_sent' => true]);
        if ($claimed !== 1) {
            return null;
        }

        $order->loadMissing('items.product.category');

        return [
            'transaction_id' => $order->number,
            'currency' => 'EUR',
            'value' => $this->euros($order->total_ttc),
            'tax' => $this->euros($order->total_vat),
            'shipping' => $this->euros($order->shipping_ttc),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'item_id' => $item->product_reference,
                'item_name' => $item->product_name,
                'item_category' => $item->product?->category?->name,
                'price' => $this->euros($item->unit_price_ttc - $item->unit_discount_ttc),
                'quantity' => $item->quantity,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function item(Product $product, int $unitPriceTtc, int $quantity): array
    {
        return [
            'item_id' => $product->reference ?: (string) $product->id,
            'item_name' => $product->name,
            'item_category' => $product->category?->name,
            'price' => $this->euros($unitPriceTtc),
            'quantity' => $quantity,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function withValue(array $items): array
    {
        $cents = 0;
        foreach ($items as $item) {
            $cents += (int) round($item['price'] * 100) * $item['quantity'];
        }

        return ['currency' => 'EUR', 'value' => $this->euros($cents), 'items' => $items];
    }

    private function euros(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
