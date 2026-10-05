<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages légales et contact (PLAN.md §16.1, §23). Contenu en placeholder et
 * non publiées : CLAUDE.md §3.1 interdit d'inventer un texte juridique —
 * le vrai contenu doit être fourni par la cliente/un juriste (voir
 * PLAN.md §27, point 14).
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['title' => 'Mentions légales', 'slug' => 'mentions-legales'],
            ['title' => 'Conditions générales de vente', 'slug' => 'cgv'],
            ['title' => 'Politique de confidentialité', 'slug' => 'politique-de-confidentialite'],
            ['title' => 'Politique de remboursement', 'slug' => 'politique-de-remboursement'],
            ['title' => 'Livraison', 'slug' => 'livraison'],
            ['title' => 'Contact', 'slug' => 'contact'],
        ];

        foreach ($pages as $page) {
            Page::query()->updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => 'Page en cours de rédaction.',
                    'is_published' => false,
                ],
            );
        }
    }
}
