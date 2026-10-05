<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClosedDateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['start_date', 'end_date', 'label'])]
class ClosedDate extends Model
{
    /** @use HasFactory<ClosedDateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
