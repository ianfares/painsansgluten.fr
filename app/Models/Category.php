<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['name', 'slug', 'description', 'icon', 'position', 'is_active', 'seo_title', 'seo_description'])]
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
     * Texte de présentation édité en RichEditor (BO) et affiché via `{!! !!}` :
     * purifié à l'écriture (liste blanche, QUALITE.md §2.3), comme la
     * description produit. Un ancien texte brut (sans balise) est gardé tel quel.
     *
     * @return Attribute<?string, ?string>
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value,
            set: fn (?string $value): ?string => $value !== null && $value !== strip_tags($value) ? Purifier::clean($value) : $value,
        );
    }

    /** HTML sûr à afficher : HTML purifié, ou texte brut échappé avec ses retours à la ligne. */
    public function descriptionHtml(): ?string
    {
        $description = $this->description;

        if ($description === null || trim($description) === '') {
            return null;
        }

        return $description === strip_tags($description) ? nl2br(e($description)) : $description;
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
        // Sans accents : « Apéritif », « Aperitif », « Épicerie », « Epicerie » sont reconnus.
        $name = Str::lower(Str::ascii($this->name));

        return match (true) {
            str_contains($name, 'viennoiserie') => '🥐',
            str_contains($name, 'patisserie') => '🍰',
            str_contains($name, 'biscuit') => '🍪',
            str_contains($name, 'aperitif') => '🫒',
            str_contains($name, 'epicerie') => '🫙',
            str_contains($name, 'pain') => '🥖',
            default => '🌾',
        };
    }

    /** Icône affichée : celle choisie dans le BO, sinon celle devinée d'après le nom. */
    public function icon(): string
    {
        $icon = trim((string) $this->getAttribute('icon'));

        return $icon !== '' ? $icon : $this->fallbackIcon();
    }
}
