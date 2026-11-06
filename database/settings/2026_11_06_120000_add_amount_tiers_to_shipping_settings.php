<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Frais de port par palier de montant du panier (06/11/2026, demande
 * d'Angélique via Ian, offre de lancement) : grille pré-remplie avec ses
 * valeurs (moins de 30 € : 9,50 € ; de 30 € à 49,99 € : 5,90 € ; dès 50 € :
 * 4,90 €, montants TTC en centimes), mais DÉSACTIVÉE : c'est l'activation
 * dans le back-office qui la met en service.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shipping.amount_tiers_enabled', false);
        $this->migrator->add('shipping.amount_tiers', [
            ['min_subtotal_ttc' => 0, 'price_ttc' => 950],
            ['min_subtotal_ttc' => 3000, 'price_ttc' => 590],
            ['min_subtotal_ttc' => 5000, 'price_ttc' => 490],
        ]);
    }
};
