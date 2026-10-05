<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShippingRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRate>
 */
class ShippingRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'min_weight_g' => 0,
            'max_weight_g' => 1000,
            'price_ttc' => 590,
        ];
    }
}
