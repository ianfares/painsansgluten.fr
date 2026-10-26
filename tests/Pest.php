<?php

declare(strict_types=1);

use App\Services\Chronopost\SoapClientFactory;
use App\Settings\ShippingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeChronopostSoapClientFactory;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Tous les tests Feature utilisent une base de test rafraîchie (RefreshDatabase).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Minimum de commande (19/10/2026, 15 € par défaut en base) neutralisé
        // pour les tests antérieurs qui utilisent de petits paniers ; il est
        // testé explicitement dans tests/Feature/Checkout/MinimumOrderTest.php.
        $settings = app(ShippingSettings::class);
        $settings->minimum_order_chronopost_ttc = 0;
        $settings->minimum_order_pickup_ttc = 0;
        // Chronopost (T11) : identifiants factices et réponse enregistrée
        // (relais de Bordeaux, exemple officiel Chronopost) — jamais d'appel réel.
        $settings->chronopost_product_code = '86';
        $settings->save();
        config(['services.chronopost.account_number' => '00000000', 'services.chronopost.password' => 'test-password']);
        app()->instance(SoapClientFactory::class, new FakeChronopostSoapClientFactory(FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml')));
    })
    ->in('Feature');
