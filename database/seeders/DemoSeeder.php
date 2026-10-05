<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Fixtures de développement (produits fictifs). JAMAIS exécuté en
 * production — voir le garde-fou dans `DatabaseSeeder::run()`
 * (CLAUDE.md §3 ; QUALITE.md §7).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('DemoSeeder ne doit jamais être exécuté en production.');
        }

        Category::query()->get()->each(function (Category $category): void {
            Product::factory()->count(5)->for($category)->create();
        });

        Product::factory()->unavailable()->for(Category::query()->firstOrFail())->create([
            'name' => 'Produit indisponible (démo)',
            'slug' => 'produit-indisponible-demo',
            'reference' => 'DEMO-INDISPO',
        ]);

        Product::factory()->notShippable()->for(Category::query()->firstOrFail())->create([
            'name' => 'Produit non expédiable (démo)',
            'slug' => 'produit-non-expediable-demo',
            'reference' => 'DEMO-NONEXP',
        ]);
    }
}
