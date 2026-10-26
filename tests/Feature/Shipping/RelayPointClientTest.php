<?php

declare(strict_types=1);

use App\Exceptions\Shipping\RelayServiceUnavailable;
use App\Services\Chronopost\RelayPointClient;
use App\Services\Chronopost\SoapClientFactory;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeChronopostSoapClientFactory;

function fakeChronopost(?string $response): FakeChronopostSoapClientFactory
{
    $fake = new FakeChronopostSoapClientFactory($response);
    app()->instance(SoapClientFactory::class, $fake);

    return $fake;
}

test('analyse la réponse Chronopost : identifiant, nom, adresse, distance et horaires par jour', function () {
    $this->travelTo('2021-04-01 10:00');
    fakeChronopost(FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml'));

    $points = app(RelayPointClient::class)->search('33000', 'Bordeaux');

    expect($points)->toHaveCount(10);
    $first = $points[0];
    expect($first->id)->toBe('8339S')
        ->and($first->name)->toBe('LA POSTE CARREPRO MERIADECK')
        ->and($first->addressLine1)->toBe('5 rue père dieuzaide')
        ->and($first->postalCode)->toBe('33000')
        ->and($first->city)->toBe('BORDEAUX')
        ->and($first->distanceMeters)->toBe(218)
        ->and(array_keys($first->openingHours))->toBe([1, 2, 3, 4, 5, 6])
        ->and($first->openingHours[6])->toBe('08:15-12:00')
        ->and($first->openingHoursLabel())->toStartWith('Lun. 08:15-12:00 12:00-17:00 · ');
});

test('garde les fermetures à venir et ignore celles déjà passées', function () {
    fakeChronopost(FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml'));

    $this->travelTo('2021-04-01 10:00');
    $closures = array_merge(...array_map(fn ($p) => $p->closures, app(RelayPointClient::class)->search('33000', 'Bordeaux')));
    expect($closures)->toContain(['from' => '2021-04-19', 'to' => '2021-04-23']);

    cache()->flush();
    $this->travelTo('2022-01-01 10:00');
    $closures = array_merge(...array_map(fn ($p) => $p->closures, app(RelayPointClient::class)->search('33000', 'Bordeaux')));
    expect($closures)->toBe([]);
});

test('demande les relais sans consignes automatiques (type C) avec le code produit du back-office', function () {
    $fake = fakeChronopost(FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml'));

    app(RelayPointClient::class)->search('50300', 'Avranches');

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0])->toContain('<type>C</type>')
        ->toContain('<productCode>86</productCode>')
        ->toContain('<zipCode>50300</zipCode>')
        ->toContain('<city>AVRANCHES</city>');
});

test('met les résultats en cache : une seconde recherche identique n\'appelle pas Chronopost', function () {
    $fake = fakeChronopost(FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml'));
    $client = app(RelayPointClient::class);

    $client->search('33000', 'Bordeaux');
    $again = $client->search('33000', ' bordeaux ');

    expect($fake->requests)->toHaveCount(1)->and($again)->toHaveCount(10);
});

test('retrouve un relais de la recherche par son identifiant, null s\'il est inconnu', function () {
    fakeChronopost(FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml'));
    $client = app(RelayPointClient::class);

    expect($client->find('33000', 'Bordeaux', '8339S')?->name)->toBe('LA POSTE CARREPRO MERIADECK')
        ->and($client->find('33000', 'Bordeaux', 'INCONNU'))->toBeNull();
});

test('une panne réseau lève RelayServiceUnavailable et n\'est pas mise en cache', function () {
    Log::spy();
    $fake = fakeChronopost(null);
    $client = app(RelayPointClient::class);

    expect(fn () => $client->search('33000', 'Bordeaux'))->toThrow(RelayServiceUnavailable::class);

    $fake->response = FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml');
    expect($client->search('33000', 'Bordeaux'))->toHaveCount(10);
    Log::shouldHaveReceived('warning')->once();
});

test('un code erreur Chronopost (identifiants refusés) lève RelayServiceUnavailable', function () {
    fakeChronopost(FakeChronopostSoapClientFactory::errorResponse(1500, 'FAILED : Vous n\'êtes pas autorisé à utiliser ce service.'));

    app(RelayPointClient::class)->search('33000', 'Bordeaux');
})->throws(RelayServiceUnavailable::class);

test('sans identifiants ou sans code produit, aucun appel n\'est fait', function () {
    $fake = fakeChronopost(FakeChronopostSoapClientFactory::fixture('relay-search-ok.xml'));
    $settings = app(ShippingSettings::class);
    $settings->chronopost_product_code = null;
    $settings->save();

    expect(fn () => app(RelayPointClient::class)->search('33000', 'Bordeaux'))->toThrow(RelayServiceUnavailable::class)
        ->and($fake->requests)->toBe([]);
});
