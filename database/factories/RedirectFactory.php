<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source' => '/'.fake()->unique()->slug(),
            'target' => '/'.fake()->slug(),
            'status_code' => 301,
            'is_active' => true,
        ];
    }
}
