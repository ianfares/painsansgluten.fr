<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceType;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'type', 'number', 'issued_at', 'snapshot', 'total_ht', 'total_vat', 'total_ttc', 'pdf_path', 'related_invoice_id'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'issued_at' => 'datetime',
            'snapshot' => 'array',
            'total_ht' => 'integer',
            'total_vat' => 'integer',
            'total_ttc' => 'integer',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_invoice_id');
    }
}
