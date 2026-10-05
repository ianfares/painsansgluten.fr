<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from' => OrderStatus::PendingPayment->value,
            'to' => OrderStatus::Paid->value,
            'actor_type' => 'system',
            'actor_id' => null,
            'comment' => null,
        ];
    }
}
