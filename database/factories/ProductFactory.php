<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Allergen;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'reference' => strtoupper(fake()->unique()->bothify('REF-####')),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price_ttc' => fake()->numberBetween(300, 1200),
            'vat_rate' => fake()->randomElement([5.50, 10.00]),
            'is_available' => true,
            'is_shippable' => true,
            'is_featured' => false,
            'is_published' => true,
            'position' => 0,
            'ingredients' => fake()->sentence(),
            'allergens_contains' => [Allergen::Gluten->value],
            'allergens_traces' => [],
            'allergen_note' => null,
            'nutrition' => [
                'kcal' => fake()->numberBetween(180, 320),
                'kj' => fake()->numberBetween(750, 1350),
                'fat' => fake()->randomFloat(1, 1, 10),
                'saturated' => fake()->randomFloat(1, 0.1, 3),
                'carbs' => fake()->randomFloat(1, 20, 50),
                'sugars' => fake()->randomFloat(1, 1, 10),
                'fiber' => fake()->randomFloat(1, 1, 8),
                'protein' => fake()->randomFloat(1, 2, 10),
                'salt' => fake()->randomFloat(2, 0.1, 1.5),
            ],
            'net_weight_g' => fake()->numberBetween(200, 500),
            'shipping_weight_g' => fake()->numberBetween(220, 550),
            'packaging' => 'Sachet kraft',
            'sale_unit' => 'À l\'unité',
            'tasting_tips' => fake()->sentence(),
            'storage' => 'À conserver au sec, à température ambiante.',
            'shelf_life' => '5 jours',
            'seo_title' => null,
            'seo_description' => null,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => ['is_available' => false]);
    }

    public function notShippable(): static
    {
        return $this->state(fn (): array => ['is_shippable' => false]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
