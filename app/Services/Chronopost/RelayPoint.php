<?php

declare(strict_types=1);

namespace App\Services\Chronopost;

/**
 * Point relais renvoyé par Chronopost (`recherchePointChronopostInter`).
 * Tableau simple à l'aller-retour (`toArray` / `fromArray`) : il est conservé
 * dans le composant Livewire et dans le snapshot de la commande.
 */
final readonly class RelayPoint
{
    /**
     * @param  array<int, string>  $openingHours  horaires par jour, 1 = lundi … 7 = dimanche (jours fermés absents)
     * @param  list<array{from: string, to: string}>  $closures  périodes de fermeture (dates Y-m-d)
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $addressLine1,
        public string $postalCode,
        public string $city,
        public int $distanceMeters,
        public array $openingHours,
        public array $closures,
    ) {}

    /** Jours de la semaine Chronopost (1 = lundi … 7 = dimanche). */
    public const DAY_LABELS = [1 => 'Lun.', 2 => 'Mar.', 3 => 'Mer.', 4 => 'Jeu.', 5 => 'Ven.', 6 => 'Sam.', 7 => 'Dim.'];

    /**
     * Horaires lisibles sur une ligne, au même format texte que les points de
     * retrait commerçants (`relay_snapshot.opening_hours`), ex. « Lun. 08:15-12:00 · Sam. 09:00-12:00 ».
     */
    public function openingHoursLabel(): string
    {
        $parts = [];
        foreach ($this->openingHours as $day => $hours) {
            $parts[] = (self::DAY_LABELS[$day] ?? '').' '.$hours;
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array{id: string, name: string, address_line1: string, postal_code: string, city: string, distance_m: int, opening_hours: array<int, string>, closures: list<array{from: string, to: string}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address_line1' => $this->addressLine1,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'distance_m' => $this->distanceMeters,
            'opening_hours' => $this->openingHours,
            'closures' => $this->closures,
        ];
    }
}
