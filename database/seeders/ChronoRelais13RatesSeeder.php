<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Grille Chrono Relais 13 fournie par Ian le 09/10/2026 (T26 A7) : montants HT
 * « tarification et suppléments inclus », par poids jusqu'à X kg (borne haute
 * incluse). Remplace toute la grille existante.
 *
 *   php artisan db:seed --class=ChronoRelais13RatesSeeder
 */
class ChronoRelais13RatesSeeder extends Seeder
{
    /** Borne haute (g) => prix HT (centimes). */
    public const RATES = [
        500 => 834, 1000 => 834, 1500 => 875, 2000 => 875, 3000 => 916, 4000 => 957,
        5000 => 998, 6000 => 1039, 7000 => 1080, 8000 => 1121, 9000 => 1162, 10000 => 1203,
        11000 => 1263, 12000 => 1322, 13000 => 1382, 14000 => 1442, 15000 => 1501, 16000 => 1561,
        17000 => 1620, 18000 => 1680, 19000 => 1740, 20000 => 1799,
    ];

    public function run(): void
    {
        DB::transaction(function () {
            ShippingRate::query()->delete();

            $min = 0;
            foreach (self::RATES as $max => $priceHt) {
                ShippingRate::query()->create(['min_weight_g' => $min, 'max_weight_g' => $max, 'price_ht' => $priceHt]);
                $min = $max + 1;
            }
        });
    }
}
