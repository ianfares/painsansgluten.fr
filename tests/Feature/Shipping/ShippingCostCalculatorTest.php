<?php

declare(strict_types=1);

use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Exceptions\Shipping\WeightOutOfRange;
use App\Models\ShippingRate;
use App\Services\Shipping\ShippingCostCalculator;
use App\Settings\ShippingSettings;

function calculator(): ShippingCostCalculator
{
    return app(ShippingCostCalculator::class);
}

test('grille vide lève ShippingNotConfigured', function () {
    expect(fn () => calculator()->forWeight(500, 1000))->toThrow(ShippingNotConfigured::class);
});

test('un poids hors grille lève WeightOutOfRange', function () {
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 1000, 'price_ttc' => 590]);

    expect(fn () => calculator()->forWeight(5000, 1000))->toThrow(WeightOutOfRange::class);
});

test('chaque tranche renvoie le bon tarif, bornes incluses', function () {
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 999, 'price_ttc' => 490]);
    ShippingRate::factory()->create(['min_weight_g' => 1000, 'max_weight_g' => 2999, 'price_ttc' => 690]);

    expect(calculator()->forWeight(0, 1000))->toBe(490)
        ->and(calculator()->forWeight(999, 1000))->toBe(490)
        ->and(calculator()->forWeight(1000, 1000))->toBe(690)
        ->and(calculator()->forWeight(2999, 1000))->toBe(690);
});

test('le franco s\'applique si le seuil est atteint et activé', function () {
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ttc' => 590]);

    $settings = app(ShippingSettings::class);
    $settings->free_shipping_enabled = true;
    $settings->free_shipping_threshold_ttc = 5000;
    $settings->save();

    expect(calculator()->forWeight(500, 5000))->toBe(0)
        ->and(calculator()->forWeight(500, 4999))->toBe(590);
});

test('le franco désactivé ne s\'applique jamais, même au-delà du seuil', function () {
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ttc' => 590]);

    $settings = app(ShippingSettings::class);
    $settings->free_shipping_enabled = false;
    $settings->free_shipping_threshold_ttc = 1000;
    $settings->save();

    expect(calculator()->forWeight(500, 9999))->toBe(590);
});
