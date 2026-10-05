<?php

declare(strict_types=1);

use App\Models\ShippingRate;
use App\Services\Settings\ConfigurationStatus;
use App\Settings\BankTransferSettings;
use App\Settings\BillingSettings;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;

test('la configuration est incomplète par défaut (aucune valeur métier renseignée)', function () {
    $status = app(ConfigurationStatus::class);

    expect($status->isComplete())->toBeFalse()
        ->and($status->missingItems())->not->toBeEmpty();
});

test('la configuration est complète une fois tous les paramètres bloquants renseignés', function () {
    $shop = app(ShopSettings::class);
    $shop->shop_name = 'Mon Sans Gluten by Angélique';
    $shop->contact_email = 'contact@painsansgluten.fr';
    $shop->save();

    $billing = app(BillingSettings::class);
    $billing->siret = '12345678901234';
    $billing->vat_number = 'FR00123456789';
    $billing->save();

    $bankTransfer = app(BankTransferSettings::class);
    $bankTransfer->iban = 'FR1420041010050500013M02606';
    $bankTransfer->bic = 'PSSTFRPPPAR';
    $bankTransfer->save();

    $shipping = app(ShippingSettings::class);
    $shipping->shipping_weekdays = ['monday', 'wednesday', 'friday'];
    $shipping->order_cutoff_time = '15:00';
    $shipping->production_lead_days = 1;
    $shipping->chronopost_product_code = '86';
    $shipping->shipping_vat_rate = 20.0;
    $shipping->save();

    ShippingRate::factory()->create();

    expect(app(ConfigurationStatus::class)->isComplete())->toBeTrue();
});
