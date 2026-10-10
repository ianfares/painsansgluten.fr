<?php

declare(strict_types=1);

use App\Models\Product;

test('le prix au kilo TTC est calculé en centimes : 4,50 € pour 500 g donne 9,00 €/kg', function () {
    $product = new Product(['price_ttc' => 450, 'net_weight_g' => 500]);

    expect($product->pricePerKgTtc())->toBe(900);
});

test('le prix au kilo est arrondi au centime, demi-centime vers le haut', function () {
    // 10,00 € pour 300 g : 3333,33 centimes/kg -> 3333
    expect((new Product(['price_ttc' => 1000, 'net_weight_g' => 300]))->pricePerKgTtc())->toBe(3333)
        // 0,02 € pour 3 g : 666,67 centimes/kg -> 667
        ->and((new Product(['price_ttc' => 2, 'net_weight_g' => 3]))->pricePerKgTtc())->toBe(667)
        // 0,01 € pour 2 kg : 0,5 centime/kg -> 1
        ->and((new Product(['price_ttc' => 1, 'net_weight_g' => 2000]))->pricePerKgTtc())->toBe(1);
});

test('le prix au kilo est null sans poids net ou avec un poids nul', function () {
    expect((new Product(['price_ttc' => 450, 'net_weight_g' => null]))->pricePerKgTtc())->toBeNull()
        ->and((new Product(['price_ttc' => 450, 'net_weight_g' => 0]))->pricePerKgTtc())->toBeNull();
});

test('le poids net s\'affiche en grammes sous 1 kg et en kilos à partir de 1 kg', function () {
    expect((new Product(['net_weight_g' => 500]))->formattedNetWeight())->toBe('500 g')
        ->and((new Product(['net_weight_g' => 1200]))->formattedNetWeight())->toBe('1,2 kg')
        ->and((new Product(['net_weight_g' => 1000]))->formattedNetWeight())->toBe('1 kg');
});

test('le poids net formaté est null sans poids net', function () {
    expect((new Product(['net_weight_g' => null]))->formattedNetWeight())->toBeNull()
        ->and((new Product(['net_weight_g' => 0]))->formattedNetWeight())->toBeNull();
});
