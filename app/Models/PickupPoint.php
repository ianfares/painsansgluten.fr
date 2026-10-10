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
 */
#[Fillable(['name', 'address_line1', 'postal_code', 'city', 'opening_hours', 'instructions', 'latitude', 'longitude', 'is_active', 'position'])]
class PickupPoint extends Model
{
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

    public function isGeocoded(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
