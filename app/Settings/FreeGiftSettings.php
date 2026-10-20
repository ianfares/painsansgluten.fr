<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Pâtisserie offerte (20/10/2026, demande de Ian) : au-delà d'un montant de
 * produits, une ligne « offerte » à 0 € est ajoutée à la commande, pour tous les
 * clients (particuliers et pros). La pâtisserie n'est pas précisée : c'est le
 * laboratoire qui choisit. Son poids s'ajoute à celui du colis.
 */
class FreeGiftSettings extends Settings
{
    public bool $enabled;

    /** Seuil en centimes TTC (produits après remise, hors frais de port). */
    public int $threshold_ttc;

    /** Désignation de la ligne (panier, commande, facture, bon de livraison). */
    public string $label;

    /** Poids ajouté au colis, en grammes. */
    public int $weight_g;

    public static function group(): string
    {
        return 'free_gift';
    }
}
