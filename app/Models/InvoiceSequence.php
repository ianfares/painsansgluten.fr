<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Compteur séquentiel par type et année (PLAN.md §12). Les incréments se
 * font sous verrou transactionnel (`lockForUpdate`), jamais par simple
 * `update()` : voir `App\Services\Invoicing` (T16).
 */
#[Fillable(['type', 'year', 'last_number'])]
class InvoiceSequence extends Model
{
    /** @use HasFactory<InvoiceSequenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
