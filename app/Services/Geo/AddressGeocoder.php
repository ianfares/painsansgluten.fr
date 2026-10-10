<?php

declare(strict_types=1);

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Géocodage via la Base Adresse Nationale (gratuit, sans clé). Ne lève jamais
 * d'exception : un échec renvoie null (une commande ne doit jamais être bloquée).
 */
class AddressGeocoder
{
    private const ENDPOINT = 'https://api-adresse.data.gouv.fr/search/';

    private const MIN_SCORE = 0.5;

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address, ?string $postalCode = null): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $params = ['q' => $address, 'limit' => 1];
        if (filled($postalCode)) {
            $params['postcode'] = $postalCode;
        }

        try {
            $response = Http::timeout(3)->acceptJson()->get(self::ENDPOINT, $params);
            if (! $response->successful()) {
                return null;
            }

            $feature = $response->json('features.0');
            $coordinates = is_array($feature) ? ($feature['geometry']['coordinates'] ?? null) : null;
            $score = is_array($feature) ? ($feature['properties']['score'] ?? 0) : 0;

            if (! is_array($coordinates) || count($coordinates) < 2 || ! is_numeric($score) || (float) $score < self::MIN_SCORE) {
                return null;
            }

            return ['lat' => (float) $coordinates[1], 'lng' => (float) $coordinates[0]];
        } catch (Throwable) {
            return null;
        }
    }
}
