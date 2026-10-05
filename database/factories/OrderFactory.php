<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 8000);
        $shipping = 590;
        $total = $subtotal + $shipping;

        return [
            'number' => 'C'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'token' => Str::random(40),
            'user_id' => null,
            'status' => OrderStatus::PendingPayment,
            'payment_method' => PaymentMethod::Stripe,
            'email' => fake()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'billing_first_name' => fake()->firstName(),
            'billing_last_name' => fake()->lastName(),
            'billing_company' => null,
            'billing_line1' => fake()->streetAddress(),
            'billing_line2' => null,
            'billing_postal_code' => fake()->postcode(),
            'billing_city' => fake()->city(),
            'billing_country' => 'FR',
            'relay_id' => fake()->bothify('RELAY-####'),
            'relay_name' => 'Tabac de la Gare',
            'relay_snapshot' => [
                'name' => 'Tabac de la Gare',
                'address_line1' => '3 avenue de la République',
                'postal_code' => '50300',
                'city' => 'Avranches',
                'country' => 'FR',
            ],
            'subtotal_ttc' => $subtotal,
            'shipping_ttc' => $shipping,
            'shipping_vat_rate' => 20.00,
            'total_ttc' => $total,
            'total_ht' => (int) round($total / 1.055),
            'total_vat' => $total - (int) round($total / 1.055),
            'planned_ship_date' => now()->addWeekday()->toDateString(),
            'tracking_number' => null,
            'cgv_accepted_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
