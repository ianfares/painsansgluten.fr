<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PickupPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Point de retrait chez un commerçant partenaire (T27-L7a). Non proposable
 * tant que ses coordonnées (géocodage) sont absentes.
 *
 * @property list<string>|null $pickup_weekdays jours de retrait (24/10/2026) ; vide = jours d'expédition
 * @property string|null $pickup_from
 * @property string|null $pickup_until
 */
#[Fillable(['name', 'address_line1', 'postal_code', 'city', 'opening_hours', 'pickup_weekdays', 'pickup_from', 'pickup_until', 'instructions', 'latitude', 'longitude', 'is_active', 'position'])]
class PickupPoint extends Model
{
    public const WEEKDAY_LABELS = ['monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi', 'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi', 'sunday' => 'dimanche'];

    /** @use HasFactory<PickupPointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'pickup_weekdays' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Points actifs et géocodés, dans l'ordre d'affichage.
     *
     * @param  Builder<PickupPoint>  $query
     * @return Builder<PickupPoint>
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('position')
            ->orderBy('id');
    }

    /** « de 9 h à 12 h 30 », ou null si la plage n'est pas renseignée (aussi pour le snapshot d'une commande). */
    public static function formatPickupHours(?string $from, ?string $until): ?string
    {
        if (blank($from) || blank($until)) {
            return null;
        }

        $format = function (string $time): string {
            [$hour, $minute] = array_map('intval', explode(':', $time));

            return $hour.' h'.($minute > 0 ? ' '.str_pad((string) $minute, 2, '0', STR_PAD_LEFT) : '');
        };

        return 'de '.$format($from).' à '.$format($until);
    }

    /** « le vendredi », « les mardi et vendredi », ou null si aucun jour de retrait n'est coché. */
    public function pickupDaysLabel(): ?string
    {
        $days = array_values(array_intersect_key(self::WEEKDAY_LABELS, array_flip($this->pickup_weekdays ?? [])));

        return match (count($days)) {
            0 => null,
            1 => 'le '.$days[0],
            default => 'les '.implode(', ', array_slice($days, 0, -1)).' et '.end($days),
        };
    }

    public function isGeocoded(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
