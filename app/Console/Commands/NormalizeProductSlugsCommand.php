<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Remet en forme les slugs produits saisis avec majuscules, accents ou
 * espaces (audit du 26/10/2026). Simulation par défaut ; `--apply` écrit en
 * base (dump préalable obligatoire en prod). Chaque ancien slug est redirigé
 * en 301 vers le nouveau (ProductSlugRedirect, via le modèle Product).
 */
class NormalizeProductSlugsCommand extends Command
{
    protected $signature = 'catalog:normalize-slugs {--apply : Enregistrer les changements (sinon simulation)}';

    protected $description = 'Met les slugs produits en minuscules sans accent ni espace, avec redirection 301 des anciens';

    public function handle(): int
    {
        $rows = [];

        foreach (Product::withTrashed()->orderBy('id')->get(['id', 'slug']) as $product) {
            $clean = Str::slug($product->slug);
            if ($clean === $product->slug) {
                continue;
            }

            $target = $this->uniqueSlug($clean, $product->id);
            $rows[] = [$product->id, $product->slug, $target];

            if ($this->option('apply')) {
                $product->slug = $target;
                $product->save();
            }
        }

        if ($rows === []) {
            $this->info('Tous les slugs sont déjà corrects.');

            return self::SUCCESS;
        }

        $this->table(['Produit', 'Ancien slug', 'Nouveau slug'], $rows);
        $this->info(count($rows).' slug(s) '.($this->option('apply') ? 'corrigé(s), anciennes adresses redirigées en 301.' : 'à corriger (simulation : relancer avec --apply).'));

        return self::SUCCESS;
    }

    private function uniqueSlug(string $slug, int $productId): string
    {
        $candidate = $slug;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $candidate)->whereKeyNot($productId)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
