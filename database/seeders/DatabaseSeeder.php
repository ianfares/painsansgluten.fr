<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * `DemoSeeder` (produits fictifs) n'est jamais lancé en production
     * (CLAUDE.md §3 ; QUALITE.md §7).
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            PageSeeder::class,
            ChronoRelais13RatesSeeder::class,
            T26ContentSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
