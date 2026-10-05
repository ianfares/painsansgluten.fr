<?php

declare(strict_types=1);

namespace App\Exceptions\Orders;

use App\Enums\OrderStatus;
use RuntimeException;

/**
 * Levée quand une transition de statut non autorisée est tentée
 * (PLAN.md §9.2). Le statut d'une commande ne doit jamais être modifié
 * autrement que via `OrderStateMachine` (CLAUDE.md §5).
 */
class InvalidOrderTransition extends RuntimeException
{
    public static function from(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Transition interdite : {$from->value} → {$to->value}.");
    }
}
