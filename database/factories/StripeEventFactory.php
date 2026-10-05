<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StripeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StripeEvent>
 */
class StripeEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => fake()->unique()->bothify('evt_###########'),
            'type' => 'checkout.session.completed',
            'processed_at' => now(),
        ];
    }
}
