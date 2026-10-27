<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Cart;
use App\Models\CheckoutBlock;
use App\Models\Order;
use App\Settings\ShippingSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Paniers abandonnés (27/10/2026, demande d'Angélique) : paniers non vides,
 * sans activité depuis une heure (une commande vide le panier, donc un panier
 * encore rempli n'a pas abouti). Montant = prix catalogue TTC actuels, hors
 * remise pro et hors port. Les paniers invités sont purgés après 30 jours
 * (cart:purge-old-guests) : les statistiques ne remontent pas au-delà.
 */
class AbandonedCartReport
{
    /** Délai sans activité avant de considérer un panier comme abandonné. */
    public const INACTIVE_MINUTES = 60;

    public function __construct(private readonly ShippingSettings $shippingSettings) {}

    /**
     * @return Builder<Cart>
     */
    public function query(): Builder
    {
        $items = DB::table('cart_items')->whereColumn('cart_items.cart_id', 'carts.id');

        return Cart::query()
            ->select('carts.*')
            ->selectSub((clone $items)->selectRaw('max(cart_items.updated_at)'), 'last_activity_at')
            ->selectSub(
                (clone $items)->join('products', 'products.id', '=', 'cart_items.product_id')
                    ->selectRaw('coalesce(sum(cart_items.quantity * products.price_ttc), 0)'),
                'subtotal_ttc'
            )
            ->whereHas('items')
            // Paniers techniques des commandes saisies dans le BO.
            ->where(fn (Builder $q) => $q->whereNull('session_id')->orWhere('session_id', 'not like', 'admin-order-%'))
            ->whereRaw('(select max(ci.updated_at) from cart_items ci where ci.cart_id = carts.id) <= ?', [now()->subMinutes(self::INACTIVE_MINUTES)]);
    }

    /**
     * @param  Builder<Cart>  $query
     * @return Builder<Cart>
     */
    public function sinceDays(Builder $query, int $days): Builder
    {
        return $query->whereRaw('(select max(ci.updated_at) from cart_items ci where ci.cart_id = carts.id) >= ?', [now()->subDays($days)]);
    }

    /**
     * @param  Builder<Cart>  $query
     * @return Builder<Cart>
     */
    public function belowMinimum(Builder $query): Builder
    {
        return $query->whereRaw(
            '(select coalesce(sum(ci.quantity * p.price_ttc), 0) from cart_items ci join products p on p.id = ci.product_id where ci.cart_id = carts.id) < ?',
            [$this->minimumTtc()]
        );
    }

    /**
     * Seuil « sous le minimum » : le plus élevé des minimums actifs (Chronopost,
     * retrait) — sous ce montant, au moins un mode de livraison est refusé.
     */
    public function minimumTtc(): int
    {
        return max($this->shippingSettings->minimum_order_chronopost_ttc, $this->shippingSettings->minimum_order_pickup_ttc);
    }

    /**
     * @return array{abandoned: int, below_minimum: int, average_ttc: int, orders: int, blocks: int, blocks_then_ordered: int}
     */
    public function stats(int $days): array
    {
        $subtotals = $this->sinceDays($this->query(), $days)->get()->pluck('subtotal_ttc')->map(fn ($v): int => (int) $v);
        $minimum = $this->minimumTtc();

        $blocks = CheckoutBlock::query()->where('created_at', '>=', now()->subDays($days));

        return [
            'abandoned' => $subtotals->count(),
            'below_minimum' => $minimum > 0 ? $subtotals->filter(fn (int $v): bool => $v < $minimum)->count() : 0,
            'average_ttc' => (int) round($subtotals->avg() ?? 0),
            // Commandes passées en ligne (hors commandes saisies dans le BO).
            'orders' => Order::query()->whereNull('created_by_admin_id')->where('created_at', '>=', now()->subDays($days))->count(),
            'blocks' => (clone $blocks)->count(),
            'blocks_then_ordered' => (clone $blocks)->whereNotNull('order_id')->count(),
        ];
    }
}
