<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Redirect;
use Illuminate\Database\Seeder;

/**
 * Redirections 301 depuis l'ancien site Shopify (PLAN.md §23).
 *
 * La règle générique `/products/{slug}` → `/produit/{slug}` n'est volontairement
 * pas seedée ici : c'est une règle de correspondance de motif, pas une
 * redirection ligne à ligne — elle sera implémentée dans le middleware de
 * redirection (T20), pas dans cette table.
 */
class RedirectSeeder extends Seeder
{
    public function run(): void
    {
        $redirects = [
            '/collections/all' => '/boutique',
            '/collections/les-pains-sans-gluten' => '/pains-sans-gluten',
            '/collections/viennoiseries-sans-gluten' => '/viennoiseries-sans-gluten',
            '/collections/nos-patisseries-sans-gluten' => '/patisseries-sans-gluten',
            '/pages/contact' => '/contact',
            '/policies/legal-notice' => '/mentions-legales',
            '/policies/terms-of-sale' => '/cgv',
            '/policies/privacy-policy' => '/politique-de-confidentialite',
            '/policies/refund-policy' => '/politique-de-remboursement',
            '/policies/shipping-policy' => '/livraison',
            '/cart' => '/panier',
            '/account*' => '/mon-compte',
        ];

        foreach ($redirects as $source => $target) {
            Redirect::query()->updateOrCreate(
                ['source' => $source],
                ['target' => $target, 'status_code' => 301, 'is_active' => true],
            );
        }
    }
}
