<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

#[Fillable(['title', 'slug', 'content', 'is_published', 'seo_title', 'seo_description'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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
     * Contenu riche purifié à l'écriture (QUALITE.md §2.3), affiché via
     * `{!! !!}` côté public.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value !== null ? Purifier::clean($value) : null,
        );
    }
}
