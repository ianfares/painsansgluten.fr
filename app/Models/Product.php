<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'category_id', 'name', 'slug', 'reference', 'short_description', 'description',
    'price_ttc', 'vat_rate', 'is_available', 'is_shippable', 'is_featured', 'is_published', 'position',
    'ingredients', 'allergens_contains', 'allergens_traces', 'allergen_note', 'nutrition',
    'net_weight_g', 'shipping_weight_g', 'packaging', 'sale_unit',
    'tasting_tips', 'storage', 'shelf_life', 'seo_title', 'seo_description',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * Collections : "main" (image principale, un seul fichier) et
     * "gallery" (galerie ordonnable) — PLAN.md §6.2, T05.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')->singleFile();
        $this->addMediaCollection('gallery');
    }

    /**
     * Conversions WebP (miniature, liste, fiche, zoom) — CLAUDE.md §2
     * (images: spatie/laravel-medialibrary, conversions WebP).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('thumbnail')
            ->nonQueued()
            ->fit(Fit::Crop, 150, 150)
            ->format('webp');

        $this
            ->addMediaConversion('liste')
            ->nonQueued()
            ->fit(Fit::Crop, 400, 400)
            ->format('webp');

        $this
            ->addMediaConversion('fiche')
            ->nonQueued()
            ->fit(Fit::Contain, 800, 800)
            ->format('webp');

        $this
            ->addMediaConversion('zoom')
            ->nonQueued()
            ->fit(Fit::Contain, 1600, 1600)
            ->format('webp');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_ttc' => 'integer',
            'vat_rate' => 'decimal:2',
            'is_available' => 'boolean',
            'is_shippable' => 'boolean',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'allergens_contains' => 'array',
            'allergens_traces' => 'array',
            'nutrition' => 'array',
            'net_weight_g' => 'integer',
            'shipping_weight_g' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Un produit n'est jamais commandable depuis le catalogue tant qu'il
     * n'est pas publié, disponible et expédiable (PLAN.md §5.3, §6.4).
     */
    public function isOrderable(): bool
    {
        return $this->is_published && $this->is_available && $this->is_shippable;
    }

    /**
     * Suppression interdite si le produit figure dans une commande
     * (CLAUDE.md §4.1) : désactivation (`is_available = false`) à la place.
     */
    public function hasBeenOrdered(): bool
    {
        return $this->orderItems()->exists();
    }
}
