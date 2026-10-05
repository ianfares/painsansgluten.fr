<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClosedDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClosedDate>
 */
class ClosedDateFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'start_date' => $start,
            'end_date' => $start,
            'label' => 'Jour férié',
        ];
    }
}
