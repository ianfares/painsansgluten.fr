<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(300, 1200);
        $quantity = fake()->numberBetween(1, 3);
        $lineTotal = $unitPrice * $quantity;

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => ucfirst(fake()->words(3, true)),
            'product_reference' => strtoupper(fake()->bothify('REF-####')),
            'unit_price_ttc' => $unitPrice,
            'vat_rate' => 5.50,
            'quantity' => $quantity,
            'weight_g' => fake()->numberBetween(200, 500),
            'line_total_ttc' => $lineTotal,
            'line_total_ht' => (int) round($lineTotal / 1.055),
        ];
    }
}
