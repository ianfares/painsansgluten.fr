<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => Str::random(40),
            'user_id' => null,
        ];
    }
}
