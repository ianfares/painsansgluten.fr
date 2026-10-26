<?php

declare(strict_types=1);

namespace App\Services\Chronopost;

use App\Exceptions\Shipping\RelayServiceUnavailable;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recherche des points relais Chronopost autour d'un code postal + ville
 * (méthode SOAP `recherchePointChronopostInter`, PLAN.md §8.1).
 *
 * - `type=C` : relais commerçants uniquement, **sans consignes automatiques**
 *   (décision de Ian du 26/10/2026 : produits frais, pas de casiers).
 * - Résultats en cache par code postal + ville (`relay_cache_minutes`) ; un
 *   échec n'est jamais mis en cache.
 * - Timeout court : le client ne doit pas rester bloqué (PLAN.md §8.1).
 */
class RelayPointClient
{
    /** Code retour Chronopost « OK » (doc Web Services §4.3.3). */
    private const SUCCESS_CODE = 0;

    /** Relais commerçants sans consignes (doc Web Services, paramètre `type`). */
    private const POINT_TYPE_WITHOUT_LOCKERS = 'C';

    /** Prestation : tous les services du produit (exemple fourni par Chronopost). */
    private const SERVICE_ALL = 'T';

    public function __construct(
        private readonly SoapClientFactory $soapClientFactory,
        private readonly ShippingSettings $shippingSettings,
    ) {}

    /**
     * @return list<RelayPoint> triés du plus proche au plus loin (ordre Chronopost)
     *
     * @throws RelayServiceUnavailable WS injoignable, en erreur ou non configuré
     */
    public function search(string $postalCode, string $city): array
    {
        $city = mb_strtoupper(trim($city));
        $key = 'chronopost:relays:'.sha1($postalCode.'|'.$city);

        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            $key,
            now()->addMinutes((int) config('services.chronopost.relay_cache_minutes')),
            fn (): array => array_map(fn (RelayPoint $point): array => $point->toArray(), $this->fetch($postalCode, $city)),
        );

        return array_map(fn (array $row): RelayPoint => self::pointFromArray($row), $rows);
    }

    /**
     * Relit un relais d'une recherche précédente (cache) : le navigateur ne
     * fournit que l'identifiant, jamais l'adresse (CLAUDE.md §3.8).
     *
     * @throws RelayServiceUnavailable
     */
    public function find(string $postalCode, string $city, string $relayId): ?RelayPoint
    {
        foreach ($this->search($postalCode, $city) as $point) {
            if ($point->id === $relayId) {
                return $point;
            }
        }

        return null;
    }

    /** @return list<RelayPoint> */
    private function fetch(string $postalCode, string $city): array
    {
        $accountNumber = (string) config('services.chronopost.account_number');
        $password = (string) config('services.chronopost.password');
        $productCode = (string) $this->shippingSettings->chronopost_product_code;

        if ($accountNumber === '' || $password === '' || $productCode === '') {
            Log::error('Chronopost : identifiants ou code produit manquants (recherche de relais impossible).');

            throw new RelayServiceUnavailable('Chronopost non configuré.');
        }

        try {
            $client = $this->soapClientFactory->make(
                (string) config('services.chronopost.relay_wsdl'),
                (int) config('services.chronopost.timeout_seconds'),
            );

            $response = $client->__soapCall('recherchePointChronopostInter', [[
                'accountNumber' => $accountNumber,
                'password' => $password,
                'address' => '',
                'zipCode' => $postalCode,
                'city' => $city,
                'countryCode' => 'FR',
                'type' => self::POINT_TYPE_WITHOUT_LOCKERS,
                'productCode' => $productCode,
                'service' => self::SERVICE_ALL,
                'weight' => '',
                'shippingDate' => CarbonImmutable::now('Europe/Paris')->format('d/m/Y'),
                'maxPointChronopost' => (string) config('services.chronopost.relay_max_results'),
                'maxDistanceSearch' => (string) config('services.chronopost.relay_max_distance_km'),
                'holidayTolerant' => '1',
                'language' => 'FR',
                'version' => '2.0',
            ]]);
        } catch (Throwable $e) {
            // Pas de données personnelles : seul le code postal (non nominatif) est journalisé.
            Log::warning('Chronopost : recherche de relais en échec.', ['postal_code' => $postalCode, 'error' => $e->getMessage()]);

            throw new RelayServiceUnavailable('Chronopost injoignable.', previous: $e);
        }

        $result = is_object($response) && isset($response->return) && is_object($response->return) ? $response->return : null;
        $errorCode = $result !== null && isset($result->errorCode) ? (int) $result->errorCode : null;

        if ($errorCode !== self::SUCCESS_CODE) {
            Log::warning('Chronopost : recherche de relais refusée.', [
                'postal_code' => $postalCode,
                'error_code' => $errorCode,
                'error_message' => $result->errorMessage ?? null,
            ]);

            throw new RelayServiceUnavailable('Réponse Chronopost en erreur.');
        }

        $points = [];
        foreach ((array) ($result->listePointRelais ?? []) as $raw) {
            if (is_object($raw) && ($raw->actif ?? false) === true) {
                $points[] = self::pointFromSoap($raw);
            }
        }

        return $points;
    }

    private static function pointFromSoap(object $raw): RelayPoint
    {
        $hours = [];
        foreach ((array) ($raw->listeHoraireOuverture ?? []) as $day) {
            $label = trim((string) ($day->horairesAsString ?? ''));
            if (is_object($day) && $label !== '' && isset($day->jour)) {
                $hours[(int) $day->jour] = $label;
            }
        }
        ksort($hours);

        $closures = [];
        foreach ((array) ($raw->listePeriodeFermeture ?? []) as $period) {
            // Fermetures déjà terminées : inutiles pour le client.
            if (is_object($period) && isset($period->calendarDeDebut, $period->calendarDeFin)
                && substr((string) $period->calendarDeFin, 0, 10) >= CarbonImmutable::now('Europe/Paris')->toDateString()) {
                $closures[] = [
                    'from' => substr((string) $period->calendarDeDebut, 0, 10),
                    'to' => substr((string) $period->calendarDeFin, 0, 10),
                ];
            }
        }

        $address = implode(' ', array_filter(array_map(
            fn (mixed $line): string => trim((string) $line),
            [$raw->adresse1 ?? '', $raw->adresse2 ?? '', $raw->adresse3 ?? ''],
        )));

        return new RelayPoint(
            id: (string) $raw->identifiant,
            name: trim((string) ($raw->nom ?? '')),
            addressLine1: $address,
            postalCode: (string) ($raw->codePostal ?? ''),
            city: trim((string) ($raw->localite ?? '')),
            distanceMeters: (int) ($raw->distanceEnMetre ?? 0),
            openingHours: $hours,
            closures: $closures,
        );
    }

    /** @param  array<string, mixed>  $row */
    private static function pointFromArray(array $row): RelayPoint
    {
        /** @var array<int, string> $hours */
        $hours = $row['opening_hours'];
        /** @var list<array{from: string, to: string}> $closures */
        $closures = $row['closures'];

        return new RelayPoint(
            id: (string) $row['id'],
            name: (string) $row['name'],
            addressLine1: (string) $row['address_line1'],
            postalCode: (string) $row['postal_code'],
            city: (string) $row['city'],
            distanceMeters: (int) $row['distance_m'],
            openingHours: $hours,
            closures: $closures,
        );
    }
}
