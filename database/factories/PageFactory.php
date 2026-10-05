<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => 'Page en cours de rédaction.',
            'is_published' => false,
            'seo_title' => null,
            'seo_description' => null,
        ];
    }
}
