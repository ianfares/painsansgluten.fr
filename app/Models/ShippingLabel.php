<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Étiquette Chronopost d'une commande (T28). Le numéro de LT
 * (`skybill_number`) est le numéro de suivi du colis.
 *
 * @property Carbon|null $cancelled_at
 */
#[Fillable(['order_id', 'skybill_number', 'reservation_number', 'pdf_path', 'weight_g', 'created_by_admin_id'])]
class ShippingLabel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_g' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @param  Builder<ShippingLabel>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }
}
