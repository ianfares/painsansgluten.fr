<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'name', 'slug', 'reference', 'short_description', 'description',
    'price_ttc', 'vat_rate', 'is_available', 'is_shippable', 'is_featured', 'is_published', 'position',
    'ingredients', 'allergens_contains', 'allergens_traces', 'allergen_note', 'nutrition',
    'net_weight_g', 'shipping_weight_g', 'packaging', 'sale_unit',
    'tasting_tips', 'storage', 'shelf_life', 'seo_title', 'seo_description',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

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
     * Un produit n'est jamais commandable depuis le catalogue tant qu'il
     * n'est pas publié, disponible et expédiable (PLAN.md §5.3, §6.4).
     */
    public function isOrderable(): bool
    {
        return $this->is_published && $this->is_available && $this->is_shippable;
    }
}
