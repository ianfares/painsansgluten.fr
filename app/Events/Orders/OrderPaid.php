<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Déclenché une seule fois au passage `paid` (PLAN.md §9.2). Les listeners
 * (facture, email — T16/T19) doivent être idempotents et en queue.
 */
class OrderPaid
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
