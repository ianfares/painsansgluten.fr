<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Les 4 catégories V1 (CLAUDE.md §1, PLAN.md §6.1).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pains', 'slug' => 'pains-sans-gluten', 'position' => 1],
            ['name' => 'Viennoiseries', 'slug' => 'viennoiseries-sans-gluten', 'position' => 2],
            ['name' => 'Pâtisseries', 'slug' => 'patisseries-sans-gluten', 'position' => 3],
            ['name' => 'Biscuits', 'slug' => 'biscuits-sans-gluten', 'position' => 4],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'is_active' => true],
            );
        }
    }
}
