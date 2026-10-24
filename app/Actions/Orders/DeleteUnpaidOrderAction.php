<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\OrderDeletionNotAllowed;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BO « Supprimer » (24/10/2026, demande de Ian) : commandes de test ou
 * jamais réglées uniquement. Une facture émise est immuable et sa commande
 * se conserve (CLAUDE.md §4) : une commande payée se rembourse, jamais ne
 * se supprime. Lignes, paiements et historique partent en cascade.
 */
class DeleteUnpaidOrderAction
{
    /** Marge après l'expiration de la session Stripe Checkout, pour les paiements en cours de validation. */
    private const STRIPE_MARGIN_MINUTES = 15;

    /** Sans requête : appelé pour chaque ligne de la liste des commandes (la facture est revérifiée à l'exécution). */
    public static function isAvailableFor(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::PaymentFailed, OrderStatus::Cancelled], true);
    }

    public function execute(Order $order, ?int $adminId): void
    {
        DB::transaction(function () use ($order, $adminId): void {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! self::isAvailableFor($locked) || $locked->invoices()->exists()) {
                throw OrderDeletionNotAllowed::notDeletable();
            }

            // Session Checkout encore ouverte : un paiement arrivant après suppression
            // ne retrouverait plus sa commande (encaissement sans commande).
            if ($locked->payment_method === PaymentMethod::Stripe && $locked->status === OrderStatus::PendingPayment) {
                $delay = (int) config('services.stripe.session_expires_minutes', 30) + self::STRIPE_MARGIN_MINUTES;
                $remaining = (int) ceil(now()->diffInMinutes($locked->created_at->copy()->addMinutes($delay), false));
                if ($remaining > 0) {
                    throw OrderDeletionNotAllowed::stripeSessionMayStillBePaid($remaining);
                }
            }

            $locked->delete();

            // Numéro et statut seulement : aucune donnée personnelle dans les logs.
            Log::info('Commande non réglée supprimée depuis le BO.', ['order' => $locked->number, 'status' => $locked->status->value, 'admin_id' => $adminId]);
        });
    }
}
