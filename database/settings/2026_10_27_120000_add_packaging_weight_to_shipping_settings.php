<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Poids de l'emballage (27/10/2026, retour d'Angélique via Ian) : 400 g en
 * moyenne pour le carton et le calage, ajoutés une fois par colis à la
 * tranche de frais de port et au poids déclaré sur l'étiquette Chronopost.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shipping.packaging_weight_g', 400);
    }
};
