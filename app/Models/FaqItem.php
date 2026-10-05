<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

#[Fillable(['question', 'answer', 'group', 'position', 'is_published'])]
class FaqItem extends Model
{
    /** @use HasFactory<FaqItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * Réponse éditée en RichEditor (BO), purifiée à l'écriture (QUALITE.md §2.3).
     */
    protected function answer(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value !== null ? Purifier::clean($value) : null,
        );
    }
}
