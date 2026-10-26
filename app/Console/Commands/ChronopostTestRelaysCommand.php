<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Shipping\RelayServiceUnavailable;
use App\Services\Chronopost\RelayPoint;
use App\Services\Chronopost\RelayPointClient;
use Illuminate\Console\Command;

/**
 * Diagnostic manuel (T01/T11) : vérifie que les identifiants Chronopost du
 * `.env` et le code produit du back-office permettent la recherche de relais.
 * Lecture seule, aucune donnée client ; le cache n'est pas utilisé (appel réel).
 */
class ChronopostTestRelaysCommand extends Command
{
    protected $signature = 'chronopost:test-relays {postal_code : Code postal, ex. 50300} {city : Ville, ex. Avranches}';

    protected $description = 'Interroge Chronopost et affiche les points relais trouvés (diagnostic)';

    public function handle(RelayPointClient $client): int
    {
        $postalCode = (string) $this->argument('postal_code');
        $city = (string) $this->argument('city');
        cache()->forget('chronopost:relays:'.sha1($postalCode.'|'.mb_strtoupper(trim($city))));

        try {
            $points = $client->search($postalCode, $city);
        } catch (RelayServiceUnavailable) {
            $this->error('Recherche impossible : identifiants, code produit ou service Chronopost en cause (détail dans le journal Laravel).');

            return self::FAILURE;
        }

        $this->table(
            ['Identifiant', 'Nom', 'Adresse', 'Distance', 'Horaires'],
            array_map(fn (RelayPoint $point): array => [
                $point->id,
                $point->name,
                $point->addressLine1.', '.$point->postalCode.' '.$point->city,
                number_format($point->distanceMeters / 1000, 1, ',', ' ').' km',
                $point->openingHoursLabel(),
            ], $points),
        );
        $this->info(count($points).' relais trouvé(s).');

        return self::SUCCESS;
    }
}
