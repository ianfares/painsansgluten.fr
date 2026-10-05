<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Cart\CartService;
use App\Services\Sequencing\SequenceGenerator;
use App\Settings\ShippingSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Crée une commande au clic « Payer » (PLAN.md §9.1) : snapshot complet,
 * numéro séquentiel, token aléatoire, **totaux recalculés côté serveur**
 * (jamais de confiance dans une valeur envoyée par le client — CLAUDE.md §4).
 */
class CreateOrderAction
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly SequenceGenerator $sequenceGenerator,
    ) {}

    /**
     * @param  array{email: string, first_name: string, last_name: string, phone: string}  $customer
     * @param  array{first_name: string, last_name: string, company?: ?string, line1: string, line2?: ?string, postal_code: string, city: string}  $billing
     * @param  array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>}  $relay
     */
    public function execute(Cart $cart, array $customer, array $billing, array $relay, PaymentMethod $paymentMethod, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($cart, $customer, $billing, $relay, $paymentMethod, $userId) {
            ['items' => $items] = $this->cartService->validItems($cart);

            if ($items->isEmpty()) {
                throw new RuntimeException('Impossible de créer une commande avec un panier vide.');
            }

            $totals = $this->cartService->totals($cart);

            if ($totals['shipping_error'] !== null) {
                throw new RuntimeException($totals['shipping_error']);
            }

            $number = $this->sequenceGenerator->next('order', 'C');

            $order = Order::query()->create([
                'number' => $number,
                'token' => Str::random(40),
                'user_id' => $userId,
                'status' => OrderStatus::PendingPayment,
                'payment_method' => $paymentMethod,
                'email' => $customer['email'],
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'phone' => $customer['phone'],
                'billing_first_name' => $billing['first_name'],
                'billing_last_name' => $billing['last_name'],
                'billing_company' => $billing['company'] ?? null,
                'billing_line1' => $billing['line1'],
                'billing_line2' => $billing['line2'] ?? null,
                'billing_postal_code' => $billing['postal_code'],
                'billing_city' => $billing['city'],
                'billing_country' => 'FR',
                'relay_id' => $relay['relay_id'],
                'relay_name' => $relay['relay_name'],
                'relay_snapshot' => $relay['relay_snapshot'],
                'subtotal_ttc' => $totals['subtotal_ttc'],
                'shipping_ttc' => $totals['shipping_ttc'],
                'shipping_vat_rate' => app(ShippingSettings::class)->shipping_vat_rate ?? 0,
                'total_ttc' => $totals['total_ttc'],
                'total_ht' => $this->computeTotalHt($totals['total_ttc'], $items),
                'total_vat' => $totals['total_ttc'] - $this->computeTotalHt($totals['total_ttc'], $items),
                'planned_ship_date' => $totals['planned_ship_date'],
                'cgv_accepted_at' => now(),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item->product->id,
                    'product_name' => $item->product->name,
                    'product_reference' => $item->product->reference,
                    'unit_price_ttc' => $item->product->price_ttc,
                    'vat_rate' => $item->product->vat_rate ?? 0,
                    'quantity' => $item->quantity,
                    'weight_g' => $item->product->shipping_weight_g,
                    'line_total_ttc' => $item->product->price_ttc * $item->quantity,
                    'line_total_ht' => (int) round(($item->product->price_ttc * $item->quantity) / (1 + (float) ($item->product->vat_rate ?? 0) / 100)),
                ]);
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from' => null,
                'to' => OrderStatus::PendingPayment->value,
                'actor_type' => 'system',
                'actor_id' => null,
                'comment' => 'Commande créée',
            ]);

            $cart->items()->delete();

            return $order->fresh(['items']);
        });
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    private function computeTotalHt(int $totalTtc, Collection $items): int
    {
        if ($items->isEmpty()) {
            return $totalTtc;
        }

        // Taux moyen pondéré des lignes pour répartir le HT (affiné en T16 avec le détail par taux).
        $weightedRate = $items->sum(fn ($item) => ((float) ($item->product->vat_rate ?? 0)) * $item->product->price_ttc * $item->quantity)
            / max(1, $items->sum(fn ($item) => $item->product->price_ttc * $item->quantity));

        return (int) round($totalTtc / (1 + $weightedRate / 100));
    }
}
