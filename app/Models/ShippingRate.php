<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShippingRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tranche de la grille transporteur. Prix saisi en HT (T26 A7), en centimes.
 */
#[Fillable(['min_weight_g', 'max_weight_g', 'price_ht'])]
class ShippingRate extends Model
{
    /** @use HasFactory<ShippingRateFactory> */
    use HasFactory;

    /** Prix TTC facturé au client, en centimes, arrondi au centime. */
    public function priceTtc(float $vatRate): int
    {
        return (int) round($this->price_ht * (1 + $vatRate / 100));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_weight_g' => 'integer',
            'max_weight_g' => 'integer',
            'price_ht' => 'integer',
        ];
    }
}
