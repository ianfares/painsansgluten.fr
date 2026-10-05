<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'token', 'user_id', 'status', 'payment_method',
    'email', 'first_name', 'last_name', 'phone',
    'billing_first_name', 'billing_last_name', 'billing_company', 'billing_line1', 'billing_line2',
    'billing_postal_code', 'billing_city', 'billing_country',
    'relay_id', 'relay_name', 'relay_snapshot',
    'subtotal_ttc', 'shipping_ttc', 'shipping_vat_rate', 'total_ttc', 'total_ht', 'total_vat',
    'planned_ship_date', 'tracking_number', 'cgv_accepted_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'relay_snapshot' => 'array',
            'subtotal_ttc' => 'integer',
            'shipping_ttc' => 'integer',
            'shipping_vat_rate' => 'decimal:2',
            'total_ttc' => 'integer',
            'total_ht' => 'integer',
            'total_vat' => 'integer',
            'planned_ship_date' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cgv_accepted_at' => 'datetime',
            'payment_anomaly' => 'boolean',
            'ga_purchase_sent' => 'boolean',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
