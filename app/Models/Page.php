<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

/**
 * @property list<array{title: string, content: ?string, show_pickup_points: bool, pickup_point_ids: list<int>}>|null $tabs onglets (24/10/2026)
 */
#[Fillable(['title', 'slug', 'content', 'tabs', 'is_published', 'seo_title', 'seo_description'])]
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
            'tabs' => 'array',
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

    /**
     * Onglets : texte de chaque onglet purifié à l'écriture, comme `content`.
     *
     * @param  array<array-key, array<string, mixed>>|null  $value  le Repeater Filament indexe par UUID
     */
    public function setTabsAttribute(?array $value): void
    {
        $this->attributes['tabs'] = $value === null ? null : json_encode(array_values(array_map(fn (array $tab): array => [
            'title' => (string) ($tab['title'] ?? ''),
            'content' => filled($tab['content'] ?? null) ? Purifier::clean((string) $tab['content']) : null,
            'show_pickup_points' => (bool) ($tab['show_pickup_points'] ?? false),
            // Points choisis pour cet onglet ; vide = tous les points actifs.
            'pickup_point_ids' => array_values(array_map('intval', (array) ($tab['pickup_point_ids'] ?? []))),
        ], $value)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
