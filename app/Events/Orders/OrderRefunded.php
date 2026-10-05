<?php

declare(strict_types=1);

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderRefunded
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
