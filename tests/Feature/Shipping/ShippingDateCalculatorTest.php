<?php

declare(strict_types=1);

use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Models\ClosedDate;
use App\Services\Shipping\ShippingDateCalculator;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;

function configureShipping(array $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], string $cutoff = '15:00', int $leadDays = 1): void
{
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = $weekdays;
    $settings->order_cutoff_time = $cutoff;
    $settings->production_lead_days = $leadDays;
    $settings->save();
}

function at(string $datetime): CarbonImmutable
{
    return CarbonImmutable::parse($datetime, 'Europe/Paris');
}

test('configuration vide lève une exception', function () {
    expect(fn () => app(ShippingDateCalculator::class)->forInstant(at('2026-03-10 10:00')))
        ->toThrow(ShippingNotConfigured::class);
});

test('avant l\'heure limite, le jour de référence compte pour le délai', function () {
    configureShipping(leadDays: 1);
    // Mardi 10 mars 2026, 10h (avant 15h) + 1 jour ouvré => mercredi 11 mars.
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-10 10:00'));

    expect($date->toDateString())->toBe('2026-03-11');
});

test('après l\'heure limite, on part du lendemain', function () {
    configureShipping(leadDays: 1);
    // Mardi 10 mars 2026, 16h (après 15h) => on part du mercredi 11 => +1 jour ouvré => jeudi 12.
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-10 16:00'));

    expect($date->toDateString())->toBe('2026-03-12');
});

test('à l\'heure limite pile, la commande est encore dans les temps (jour même) — algorithme PLAN.md §8.4 : strictement après l\'heure limite', function () {
    configureShipping(leadDays: 0);
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-10 15:00:00'));

    expect($date->toDateString())->toBe('2026-03-10');
});

test('délai de fabrication 0 : expédition le jour même si avant l\'heure limite et jour ouvré', function () {
    configureShipping(leadDays: 0);
    // Mardi 10 mars, 10h, mardi est un jour d'expédition => le jour même.
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-10 10:00'));

    expect($date->toDateString())->toBe('2026-03-10');
});

test('veille de week-end : le délai pousse au-delà du samedi/dimanche (non jours d\'expédition)', function () {
    configureShipping(leadDays: 1);
    // Vendredi 13 mars 2026, 10h + 1 jour ouvré => samedi (fermé car pas jour d'expédition) => lundi 16 mars.
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-13 10:00'));

    expect($date->toDateString())->toBe('2026-03-16');
});

test('jours fermés consécutifs sont sautés', function () {
    configureShipping(leadDays: 1);
    ClosedDate::factory()->create(['start_date' => '2026-03-11', 'end_date' => '2026-03-12', 'label' => 'Congés']);
    // Mardi 10 mars 10h + 1 jour ouvré en sautant 11-12 (fermés) => jeudi 12 compte pas, prochain jour ouvré non fermé = vendredi 13.
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-10 10:00'));

    expect($date->toDateString())->toBe('2026-03-13');
});

test('une période fermée chevauchant un week-end est bien prise en compte', function () {
    configureShipping(leadDays: 1);
    // Fermeture du vendredi au lundi inclus.
    ClosedDate::factory()->create(['start_date' => '2026-03-13', 'end_date' => '2026-03-16', 'label' => 'Pont']);
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-12 10:00'));

    expect($date->toDateString())->toBe('2026-03-17');
});

test('délai de fabrication de 2 jours ouvrés', function () {
    configureShipping(leadDays: 2);
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-10 10:00'));

    expect($date->toDateString())->toBe('2026-03-12');
});

test('le passage d\'une année à l\'autre fonctionne', function () {
    configureShipping(leadDays: 1);
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-12-30 10:00'));

    expect($date->toDateString())->toBe('2026-12-31');
});

test('le changement d\'heure (passage heure d\'été) ne décale pas le calcul', function () {
    configureShipping(leadDays: 1);
    // Nuit du 28 au 29 mars 2026 : passage à l'heure d'été en France.
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-03-27 10:00'));

    expect($date->toDateString())->toBe('2026-03-30');
});

test('le formatage français est correct', function () {
    configureShipping(leadDays: 1);
    $date = app(ShippingDateCalculator::class)->forInstant(at('2026-10-06 10:00'));

    expect(app(ShippingDateCalculator::class)->formatFrench($date))->toContain('octobre');
});
