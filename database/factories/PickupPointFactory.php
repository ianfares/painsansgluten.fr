<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PickupPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickupPoint>
 */
class PickupPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address_line1' => '1 place Littré',
            'postal_code' => '50300',
            'city' => 'Avranches',
            'opening_hours' => 'Du mardi au samedi, 9h-12h30 / 14h-19h',
            'instructions' => null,
            'latitude' => 48.6840000,
            'longitude' => -1.3570000,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function notGeocoded(): static
    {
        return $this->state(['latitude' => null, 'longitude' => null]);
    }
}
