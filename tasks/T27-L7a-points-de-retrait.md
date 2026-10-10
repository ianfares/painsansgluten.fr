# T27-L7a — Points de retrait chez des commerçants (admin + géocodage) (modèle : Sonnet 5.5)

Branche : `feature/T27-L7a-points-de-retrait`. Lire `tasks/T27-README.md`.

## À faire
- Modèle `PickupPoint` + migration : `name`, `address_line1`, `postal_code`, `city`, `opening_hours` (texte libre), `instructions` (texte libre, nullable), `latitude`/`longitude` (decimal 10,7 nullable), `is_active` bool, `position` int, timestamps.
- Ressource Filament « Points de retrait » (groupe « Expédition » ou celui des tarifs de port) : CRUD, tri par `position`, actif/inactif.
- **Géocodage** : service `App\Services\Geo\AddressGeocoder` qui appelle la **Base Adresse Nationale** (`https://api-adresse.data.gouv.fr/search/?q=...&postcode=...&limit=1`, gratuit, sans clé) via le client HTTP Laravel, timeout 3 s, retourne `?array{lat, lng}` (null si échec / score < 0,5). Géocodé à l'enregistrement d'un point ; si échec → notification Filament « Adresse introuvable, vérifiez-la » et point non proposable tant que lat/lng manquent.
- Service `App\Services\Geo\Distance::km(lat1, lng1, lat2, lng2): float` (formule de haversine).
- Réglage `shipping.lab_pickup_instructions` (texte, settings migration, champ dans `ManageShippingSettings`) pour le retrait au labo (utilisé en L7b).
- Réglage `shipping.pickup_max_distance_km` (int, défaut 50) dans `ManageShippingSettings`.
- Aucune intégration au tunnel ici (c'est L7b).

## Tests
CRUD admin, géocodage simulé (`Http::fake`) succès / échec / timeout, haversine (Avranches ↔ Granville ≈ 23 km à ±1 km), réglages présents. `LivewireRoundTripTest` : ajouter la page liste des points.
