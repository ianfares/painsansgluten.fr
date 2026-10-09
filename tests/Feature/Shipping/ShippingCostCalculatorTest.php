<?php

declare(strict_types=1);

use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Exceptions\Shipping\WeightOutOfRange;
use App\Models\ShippingRate;
use App\Services\Shipping\ShippingCostCalculator;
use App\Settings\ShippingSettings;
use Database\Seeders\ChronoRelais13RatesSeeder;

function calculator(): ShippingCostCalculator
{
    return app(ShippingCostCalculator::class);
}

function setShippingVat(?float $rate): void
{
    $settings = app(ShippingSettings::class);
    $settings->shipping_vat_rate = $rate;
    $settings->save();
}

test('grille vide lève ShippingNotConfigured', function () {
    expect(fn () => calculator()->forWeight(500, 1000))->toThrow(ShippingNotConfigured::class);
});

test('un poids hors grille lève WeightOutOfRange', function () {
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 1000, 'price_ht' => 500]);

    expect(fn () => calculator()->forWeight(5000, 1000))->toThrow(WeightOutOfRange::class);
});

test('le prix client est le HT de la tranche + la TVA du port, arrondi au centime', function () {
    setShippingVat(20.0);
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 1000, 'price_ht' => 834]);

    expect(calculator()->forWeight(500, 1000))->toBe(1001); // 8,34 × 1,2 = 10,008 €

    setShippingVat(5.5);
    expect(calculator()->forWeight(500, 1000))->toBe(880); // 8,34 × 1,055 = 8,7987 €
});

test('grille Chrono Relais 13 : chaque borne tombe dans la bonne tranche, > 20 kg impossible', function () {
    setShippingVat(20.0);
    $this->seed(ChronoRelais13RatesSeeder::class);

    expect(ShippingRate::query()->count())->toBe(22)
        ->and(calculator()->forWeight(1, 0))->toBe(1001)       // 8,34 HT
        ->and(calculator()->forWeight(500, 0))->toBe(1001)     // 8,34 HT
        ->and(calculator()->forWeight(510, 0))->toBe(1001)     // tranche 1 kg : 8,34 HT
        ->and(calculator()->forWeight(1000, 0))->toBe(1001)
        ->and(calculator()->forWeight(1010, 0))->toBe(1050)    // 8,75 HT
        ->and(calculator()->forWeight(10000, 0))->toBe(1444)   // 12,03 HT
        ->and(calculator()->forWeight(20000, 0))->toBe(2159);  // 17,99 HT

    expect(fn () => calculator()->forWeight(20010, 0))->toThrow(WeightOutOfRange::class);
});

test('relancer le seeder remplace la grille sans doublon', function () {
    $this->seed(ChronoRelais13RatesSeeder::class);
    $this->seed(ChronoRelais13RatesSeeder::class);

    expect(ShippingRate::query()->count())->toBe(22);
});

test('le franco s\'applique si le seuil est atteint et activé', function () {
    setShippingVat(20.0);
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $settings = app(ShippingSettings::class);
    $settings->free_shipping_enabled = true;
    $settings->free_shipping_threshold_ttc = 5000;
    $settings->save();

    expect(calculator()->forWeight(500, 5000))->toBe(0)
        ->and(calculator()->forWeight(500, 4999))->toBe(600);
});

test('le franco désactivé ne s\'applique jamais, même au-delà du seuil', function () {
    setShippingVat(20.0);
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);

    $settings = app(ShippingSettings::class);
    $settings->free_shipping_enabled = false;
    $settings->free_shipping_threshold_ttc = 1000;
    $settings->save();

    expect(calculator()->forWeight(500, 9999))->toBe(600);
});
