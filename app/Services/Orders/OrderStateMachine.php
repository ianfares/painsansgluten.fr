<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\Orders\OrderCancelled;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderRefunded;
use App\Events\Orders\OrderShipped;
use App\Exceptions\Orders\InvalidOrderTransition;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * Seul point d'entrée pour changer le statut d'une commande (PLAN.md §9.2,
 * CLAUDE.md §5). Toute transition est verrouillée (`lockForUpdate`),
 * historisée, et déclenche son événement **une seule fois**.
 */
class OrderStateMachine
{
    /**
     * @throws InvalidOrderTransition si la transition n'est pas autorisée.
     */
    public function transition(Order $order, OrderStatus $to, string $actorType, ?int $actorId = null, ?string $comment = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actorType, $actorId, $comment) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidOrderTransition::from($from, $to);
            }

            $locked->status = $to;
            match ($to) {
                OrderStatus::Paid => $locked->paid_at = now(),
                OrderStatus::Shipped => $locked->shipped_at = now(),
                OrderStatus::Delivered => $locked->delivered_at = now(),
                OrderStatus::Cancelled => $locked->cancelled_at = now(),
                default => null,
            };
            $locked->save();

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from' => $from->value,
                'to' => $to->value,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'comment' => $comment,
            ]);

            match ($to) {
                OrderStatus::Paid => OrderPaid::dispatch($locked),
                OrderStatus::Shipped => OrderShipped::dispatch($locked),
                OrderStatus::Cancelled => OrderCancelled::dispatch($locked),
                OrderStatus::Refunded => OrderRefunded::dispatch($locked),
                default => null,
            };

            return $locked;
        });
    }
}
