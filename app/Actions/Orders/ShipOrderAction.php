<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * Action BO « Expédier » (PLAN.md §14, T17) : un numéro de suivi est
 * obligatoire avant de passer la commande à `shipped` — sans lui, aucun
 * email de suivi ne pourrait être envoyé au client (T19) ni aucun lien de
 * suivi affiché dans l'espace client.
 */
class ShipOrderAction
{
    public function __construct(private readonly OrderStateMachine $orderStateMachine) {}

    public function execute(Order $order, string $trackingNumber, int $adminId): Order
    {
        return DB::transaction(function () use ($order, $trackingNumber, $adminId) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->tracking_number = $trackingNumber;
            $locked->save();

            return $this->orderStateMachine->transition(
                $locked,
                OrderStatus::Shipped,
                actorType: 'admin',
                actorId: $adminId,
                comment: "Expédiée, n° de suivi {$trackingNumber}.",
            );
        });
    }
}
