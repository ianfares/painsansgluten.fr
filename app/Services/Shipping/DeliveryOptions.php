<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Models\PickupPoint;
use App\Models\User;
use App\Services\Geo\AddressGeocoder;
use App\Services\Geo\Distance;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Modes de retrait proposés dans le tunnel (T27-L7b), recalculés côté serveur
 * à l'affichage ET à la création de la commande :
 * - commerçants partenaires à ≤ `pickup_max_distance_km` de l'adresse saisie
 *   par le client (vol d'oiseau) ; adresse introuvable ou BAN injoignable →
 *   aucun commerçant, Chronopost reste proposé (jamais de blocage) ;
 * - laboratoire : uniquement pour un pro validé autorisé dans l'admin.
 */
class DeliveryOptions
{
    public function __construct(
        private readonly AddressGeocoder $geocoder,
        private readonly ShippingSettings $shippingSettings,
        private readonly ShopSettings $shopSettings,
    ) {}

    /**
     * @return Collection<int, array{point: PickupPoint, distance_km: float}> triés du plus proche au plus loin
     */
    public function merchantPointsNear(string $line1, string $postalCode, string $city): Collection
    {
        $points = PickupPoint::query()->bookable()->get();
        if ($points->isEmpty()) {
            return collect(); // aucun commerçant : pas d'appel à la BAN
        }

        $client = $this->geocodeClient($line1, $postalCode, $city);
        if ($client === null) {
            return collect();
        }

        $maxKm = (float) $this->shippingSettings->pickup_max_distance_km;

        return $points
            ->map(fn (PickupPoint $point) => [
                'point' => $point,
                'distance_km' => Distance::km($client['lat'], $client['lng'], (float) $point->latitude, (float) $point->longitude),
            ])
            ->filter(fn (array $option) => $option['distance_km'] <= $maxKm)
            ->sortBy('distance_km')
            ->values();
    }

    /** @return array{point: PickupPoint, distance_km: float}|null le point demandé, s'il est bien proposable à ce client */
    public function eligibleMerchantPoint(int $pickupPointId, string $line1, string $postalCode, string $city): ?array
    {
        return $this->merchantPointsNear($line1, $postalCode, $city)
            ->first(fn (array $option) => $option['point']->id === $pickupPointId);
    }

    public function labPickupAllowed(?User $user): bool
    {
        return $user !== null && ! $user->isDeactivated() && $user->isApprovedPro() && $user->lab_pickup_allowed;
    }

    /**
     * Snapshot stocké dans `orders.relay_*` pour un retrait.
     *
     * @return array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>}
     */
    public function merchantSnapshot(PickupPoint $point, float $distanceKm): array
    {
        return [
            'relay_id' => 'COMMERCANT-'.$point->id,
            'relay_name' => $point->name,
            'relay_snapshot' => [
                'pickup_point_id' => $point->id,
                'name' => $point->name,
                'address_line1' => $point->address_line1,
                'postal_code' => $point->postal_code,
                'city' => $point->city,
                'opening_hours' => $point->opening_hours,
                'instructions' => $point->instructions,
                'distance_km' => round($distanceKm, 1),
            ],
        ];
    }

    /** @return array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>} */
    public function labSnapshot(): array
    {
        $name = 'Laboratoire '.($this->shopSettings->shop_name ?? '');

        return [
            'relay_id' => 'LABO',
            'relay_name' => trim($name),
            'relay_snapshot' => [
                'name' => trim($name),
                'address_line1' => $this->shopSettings->address_line1,
                'postal_code' => $this->shopSettings->postal_code,
                'city' => $this->shopSettings->city,
                'opening_hours' => null,
                'instructions' => $this->shippingSettings->lab_pickup_instructions,
            ],
        ];
    }

    /**
     * Coordonnées de l'adresse du client, mises en cache 24 h (clé hachée :
     * aucune adresse en clair dans le cache). Un échec n'est pas mis en cache.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function geocodeClient(string $line1, string $postalCode, string $city): ?array
    {
        $address = trim("{$line1} {$postalCode} {$city}");
        if ($address === '' || ! preg_match('/^\d{5}$/', $postalCode)) {
            return null;
        }

        $key = 'geo:client:'.sha1(mb_strtolower($address));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $coordinates = $this->geocoder->geocode($address, $postalCode);
        if ($coordinates !== null) {
            Cache::put($key, $coordinates, now()->addDay());
        }

        return $coordinates;
    }
}
