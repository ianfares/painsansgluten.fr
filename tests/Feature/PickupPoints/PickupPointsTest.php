<?php

declare(strict_types=1);

use App\Filament\Resources\PickupPointResource\Pages\CreatePickupPoint;
use App\Filament\Resources\PickupPointResource\Pages\EditPickupPoint;
use App\Filament\Resources\PickupPointResource\Pages\ListPickupPoints;
use App\Models\Admin;
use App\Models\PickupPoint;
use App\Services\Geo\AddressGeocoder;
use App\Services\Geo\Distance;
use App\Settings\ShippingSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->actingAs(Admin::factory()->create(), 'admin');
});

function banResponse(float $score = 0.9): array
{
    return ['features' => [[
        'geometry' => ['coordinates' => [-1.3571, 48.6841]],
        'properties' => ['score' => $score],
    ]]];
}

function pickupFormData(): array
{
    return [
        'name' => 'Boulangerie Dupont',
        'address_line1' => '1 place Littré',
        'postal_code' => '50300',
        'city' => 'Avranches',
        'opening_hours' => 'Mardi-samedi 9h-19h',
        'is_active' => true,
        'position' => 1,
    ];
}

test('la liste des points de retrait s\'affiche', function () {
    $point = PickupPoint::factory()->create();

    Livewire::test(ListPickupPoints::class)->assertCanSeeTableRecords([$point]);
    $this->get('/admin/pickup-points')->assertOk();
});

test('création : l\'adresse est géocodée et enregistrée', function () {
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(banResponse())]);

    Livewire::test(CreatePickupPoint::class)->fillForm(pickupFormData())->call('create')->assertHasNoFormErrors();

    $point = PickupPoint::firstOrFail();
    expect($point->latitude)->toBe(48.6841)->and($point->longitude)->toBe(-1.3571)->and($point->isGeocoded())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'postcode=50300') && str_contains($request->url(), 'limit=1'));
});

test('création : adresse introuvable → point créé mais non proposable, avec notification', function () {
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(banResponse(0.2))]);

    Livewire::test(CreatePickupPoint::class)->fillForm(pickupFormData())->call('create')
        ->assertNotified('Adresse introuvable, vérifiez-la');

    expect(PickupPoint::firstOrFail()->isGeocoded())->toBeFalse()
        ->and(PickupPoint::bookable()->count())->toBe(0);
});

test('modification : recalcule les coordonnées', function () {
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(banResponse())]);
    $point = PickupPoint::factory()->notGeocoded()->create();

    Livewire::test(EditPickupPoint::class, ['record' => $point->getRouteKey()])
        ->fillForm(['city' => 'Granville'])->call('save')->assertHasNoFormErrors();

    expect($point->refresh()->isGeocoded())->toBeTrue()->and($point->city)->toBe('Granville');
});

test('le scope bookable exclut inactifs et non géocodés, trié par position', function () {
    PickupPoint::factory()->create(['name' => 'B', 'position' => 2]);
    PickupPoint::factory()->create(['name' => 'A', 'position' => 1]);
    PickupPoint::factory()->create(['is_active' => false]);
    PickupPoint::factory()->notGeocoded()->create();

    expect(PickupPoint::bookable()->pluck('name')->all())->toBe(['A', 'B']);
});

test('géocodeur : succès, score trop bas, erreur HTTP et timeout', function () {
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::sequence()
        ->push(banResponse())
        ->push(banResponse(0.3))
        ->push([], 500)
        ->push(['features' => []])
        ->pushFailedConnection(),
    ]);
    $geocoder = new AddressGeocoder;

    expect($geocoder->geocode('1 place Littré', '50300'))->toBe(['lat' => 48.6841, 'lng' => -1.3571])
        ->and($geocoder->geocode('xyz'))->toBeNull()
        ->and($geocoder->geocode('xyz'))->toBeNull()
        ->and($geocoder->geocode('xyz'))->toBeNull()
        ->and($geocoder->geocode('xyz'))->toBeNull()
        ->and($geocoder->geocode('  '))->toBeNull();
});

test('géocodeur : une exception de connexion renvoie null', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect((new AddressGeocoder)->geocode('1 place Littré', '50300'))->toBeNull();
});

test('haversine : Avranches ↔ Granville ≈ 24 km à vol d\'oiseau (fiche : 23 km)', function () {
    $km = Distance::km(48.6840, -1.3570, 48.8385, -1.5970);

    expect($km)->toBeGreaterThan(22.0)->toBeLessThan(26.0)
        ->and(Distance::km(48.0, 2.0, 48.0, 2.0))->toBe(0.0);
});

test('les réglages de retrait existent avec leurs valeurs par défaut', function () {
    $settings = app(ShippingSettings::class);

    expect($settings->pickup_max_distance_km)->toBe(50)->and($settings->lab_pickup_instructions)->toBeNull();
    $this->get('/admin/manage-shipping-settings')->assertOk()->assertSee('Distance maximale des points de retrait');
});
