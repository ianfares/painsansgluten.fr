<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['name', 'slug', 'description', 'position', 'is_active', 'seo_title', 'seo_description'])]
class Category extends Model implements HasMedia
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Visuel de catégorie (méga-menu, PLAN.md §5.1 — T06).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('menu')
            ->nonQueued()
            ->fit(Fit::Crop, 200, 200)
            ->format('webp');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Pictogramme de repli (méga-menu, grille catégories) tant qu'aucune
     * image de couverture n'est importée (T06/T07). Choisi d'après le nom
     * de la catégorie (déjà saisi par la cliente), jamais une donnée
     * inventée — juste un repli visuel plus parlant qu'une icône unique.
     */
    public function fallbackIcon(): string
    {
        $name = mb_strtolower($this->name);

        return match (true) {
            str_contains($name, 'viennoiserie') => '🥐',
            str_contains($name, 'pâtisserie'), str_contains($name, 'patisserie') => '🍰',
            str_contains($name, 'biscuit') => '🍪',
            str_contains($name, 'pain') => '🥖',
            default => '🌾',
        };
    }
}
