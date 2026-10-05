<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqItem>
 */
class FaqItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question' => fake()->sentence().' ?',
            'answer' => fake()->paragraph(),
            'group' => 'Livraison',
            'position' => 0,
            'is_published' => true,
        ];
    }
}
