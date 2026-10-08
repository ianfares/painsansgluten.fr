<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Création de la session Stripe Checkout hébergée (T14, PLAN.md §10).
 * Tous les montants viennent de la commande en BDD (CLAUDE.md §3.8) ;
 * cette classe ne change jamais le statut : seul le webhook le fait.
 */
class StripeCheckoutService
{
    public function __construct(private readonly StripeClient $stripe) {}

    /**
     * @return string URL de la page de paiement Stripe
     */
    public function createSession(Order $order): string
    {
        $order->loadMissing('items');

        $lineItems = $order->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $item->unit_price_ttc,
                'product_data' => ['name' => $item->product_name],
            ],
        ])->all();

        if ($order->shipping_ttc > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $order->shipping_ttc,
                    'product_data' => ['name' => 'Livraison Chronopost Relais'],
                ],
            ];
        }

        // Garde-fou : ce que Stripe facturera doit être exactement le total de la commande.
        $sent = collect($lineItems)->sum(fn (array $l) => $l['quantity'] * $l['price_data']['unit_amount']);
        if ($sent !== $order->total_ttc) {
            throw new RuntimeException("Montant Stripe ({$sent}) différent du total de la commande {$order->number} ({$order->total_ttc}).");
        }

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'locale' => 'fr',
            'customer_email' => $order->email,
            'client_reference_id' => (string) $order->id,
            'metadata' => ['order_id' => (string) $order->id, 'order_number' => $order->number],
            'line_items' => $lineItems,
            'expires_at' => now()->addMinutes((int) config('services.stripe.session_expires_minutes', 30))->getTimestamp(),
            'success_url' => route('checkout.confirmation', $order),
            'cancel_url' => route('checkout.confirmation', $order),
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::Stripe,
            'provider_ref' => $session->id,
            'amount' => $order->total_ttc,
            'status' => 'pending',
        ]);

        return (string) $session->url;
    }
}
