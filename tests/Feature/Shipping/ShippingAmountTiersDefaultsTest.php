<?php

declare(strict_types=1);

use App\Settings\ShippingSettings;

test('migration : grille d\'Angélique pré-remplie mais désactivée', function () {
    $settings = app(ShippingSettings::class);

    expect($settings->amount_tiers_enabled)->toBeFalse()
        ->and($settings->amount_tiers)->toBe([
            ['min_subtotal_ttc' => 0, 'price_ttc' => 950],
            ['min_subtotal_ttc' => 3000, 'price_ttc' => 590],
            ['min_subtotal_ttc' => 5000, 'price_ttc' => 490],
        ]);
});
