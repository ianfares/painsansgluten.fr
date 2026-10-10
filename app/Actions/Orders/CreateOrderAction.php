<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Cart\CartService;
use App\Services\Pricing\CustomerPricing;
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
    public function execute(Cart $cart, array $customer, array $billing, array $relay, PaymentMethod $paymentMethod, ?int $userId = null, DeliveryMethod $deliveryMethod = DeliveryMethod::ChronopostRelay): Order
    {
        return DB::transaction(function () use ($cart, $customer, $billing, $relay, $paymentMethod, $userId, $deliveryMethod) {
            // Verrou sur le panier : deux validations simultanées (double clic,
            // deux onglets) ne créent qu'une commande, la seconde trouve le panier vide.
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            ['items' => $items] = $this->cartService->validItems($cart);

            if ($items->isEmpty()) {
                throw new RuntimeException('Impossible de créer une commande avec un panier vide.');
            }

            $totals = $this->cartService->totals($cart, $deliveryMethod);

            if ($totals['shipping_error'] !== null) {
                throw new RuntimeException($totals['shipping_error']);
            }

            $number = $this->sequenceGenerator->next('order', 'C');
            $shippingVatRate = (float) (app(ShippingSettings::class)->shipping_vat_rate ?? 0);
            $percent = $totals['discount_percent'];
            $totalHt = $this->computeTotalHt($items, $percent, $totals['shipping_ttc'], $shippingVatRate);

            $order = Order::query()->create([
                'number' => $number,
                'token' => Str::random(40),
                'user_id' => $userId,
                'status' => OrderStatus::PendingPayment,
                'payment_method' => $paymentMethod,
                'delivery_method' => $deliveryMethod,
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
                'discount_percent' => $percent,
                'discount_total_ttc' => $totals['discount_ttc'],
                'shipping_ttc' => $totals['shipping_ttc'],
                'shipping_vat_rate' => $shippingVatRate,
                'total_ttc' => $totals['total_ttc'],
                'total_ht' => $totalHt,
                'total_vat' => $totals['total_ttc'] - $totalHt,
                'planned_ship_date' => $totals['planned_ship_date'],
                'cgv_accepted_at' => now(),
            ]);

            foreach ($items as $item) {
                $lineTtc = CustomerPricing::unitNet($item->product->price_ttc, $percent) * $item->quantity;
                $order->items()->create([
                    'product_id' => $item->product->id,
                    'product_name' => $item->product->name,
                    'product_reference' => $item->product->reference,
                    'unit_price_ttc' => $item->product->price_ttc,
                    'unit_discount_ttc' => CustomerPricing::unitDiscount($item->product->price_ttc, $percent),
                    'vat_rate' => $item->product->vat_rate ?? 0,
                    'quantity' => $item->quantity,
                    'weight_g' => $item->product->shipping_weight_g,
                    'line_total_ttc' => $lineTtc,
                    'line_total_ht' => (int) round($lineTtc / (1 + (float) ($item->product->vat_rate ?? 0) / 100)),
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
     * Total HT = somme des HT de chaque ligne (à son propre taux) + HT du port
     * (au taux de TVA du port). Même calcul que les lignes de la facture ; les
     * lignes sont prises après remise client (T27-L6).
     *
     * @param  Collection<int, CartItem>  $items
     */
    private function computeTotalHt(Collection $items, int $percent, int $shippingTtc, float $shippingVatRate): int
    {
        $linesHt = $items->sum(fn (CartItem $item) => (int) round(
            (CustomerPricing::unitNet($item->product->price_ttc, $percent) * $item->quantity) / (1 + (float) ($item->product->vat_rate ?? 0) / 100)
        ));

        return $linesHt + (int) round($shippingTtc / (1 + $shippingVatRate / 100));
    }
}
