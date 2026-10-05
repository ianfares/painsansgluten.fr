<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * SEO global (repli par défaut, PLAN.md §17) : utilisé quand une page,
 * catégorie ou produit n'a pas de meta title/description propres.
 */
class SeoSettings extends Settings
{
    public ?string $default_title;

    public ?string $default_description;

    public ?string $default_og_image_path;

    public static function group(): string
    {
        return 'seo';
    }
}
