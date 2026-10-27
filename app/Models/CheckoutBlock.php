<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refus de commande dans le tunnel (minimum non atteint), sans donnée
 * personnelle : sert aux statistiques de la page « Paniers abandonnés ».
 */
#[Fillable(['cart_id', 'reason', 'delivery_method', 'subtotal_ttc', 'minimum_ttc', 'order_id'])]
class CheckoutBlock extends Model
{
    public const REASON_MINIMUM_ORDER = 'minimum_order';

    protected function casts(): array
    {
        return [
            'delivery_method' => DeliveryMethod::class,
            'subtotal_ttc' => 'integer',
            'minimum_ttc' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
