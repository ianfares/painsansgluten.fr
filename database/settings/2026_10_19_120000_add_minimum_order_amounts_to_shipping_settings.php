<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Montants minimum de commande (19/10/2026, demande de Ian) : 15 € par défaut
 * pour chaque mode, modifiables dans Paramètres > Expédition. 0 = pas de minimum.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shipping.minimum_order_chronopost_ttc', 1500);
        $this->migrator->add('shipping.minimum_order_pickup_ttc', 1500);
    }
};
