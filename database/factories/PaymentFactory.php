<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::Stripe,
            'provider_ref' => fake()->bothify('cs_test_###########'),
            'amount' => fake()->numberBetween(1000, 9000),
            'status' => 'succeeded',
            'raw' => null,
        ];
    }
}
