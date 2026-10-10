<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Settings\ShippingSettings;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property OrderStatus $status
 * @property PaymentMethod $payment_method
 * @property DeliveryMethod $delivery_method
 * @property array<string, mixed>|null $relay_snapshot
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $bank_transfer_reminder_sent_at
 * @property Carbon|null $planned_ship_date
 */
#[Fillable([
    'number', 'token', 'user_id', 'status', 'payment_method', 'delivery_method',
    'email', 'first_name', 'last_name', 'phone',
    'billing_first_name', 'billing_last_name', 'billing_company', 'billing_line1', 'billing_line2',
    'billing_postal_code', 'billing_city', 'billing_country',
    'relay_id', 'relay_name', 'relay_snapshot',
    'subtotal_ttc', 'discount_percent', 'discount_total_ttc', 'shipping_ttc', 'shipping_vat_rate', 'total_ttc', 'total_ht', 'total_vat',
    'planned_ship_date', 'tracking_number', 'cgv_accepted_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** Valeur par défaut aussi côté modèle (comme la colonne) : une commande neuve est en Chronopost Relais. */
    protected $attributes = ['delivery_method' => 'chronopost_relay'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'delivery_method' => DeliveryMethod::class,
            'relay_snapshot' => 'array',
            'subtotal_ttc' => 'integer',
            'discount_percent' => 'integer',
            'discount_total_ttc' => 'integer',
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
            'bank_transfer_reminder_sent_at' => 'datetime',
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

    /**
     * Lien de suivi Chronopost (modèle paramétré en BO, jeton {tracking}),
     * ou null si pas de numéro ou pas de modèle.
     */
    public function trackingUrl(): ?string
    {
        $template = app(ShippingSettings::class)->tracking_url_template;

        return $template && $this->tracking_number
            ? str_replace('{tracking}', rawurlencode($this->tracking_number), $template)
            : null;
    }
}
