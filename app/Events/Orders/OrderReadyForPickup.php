<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Commande prête au retrait (commerçant ou laboratoire, T27-L7b). */
class OrderReadyForPickup
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
