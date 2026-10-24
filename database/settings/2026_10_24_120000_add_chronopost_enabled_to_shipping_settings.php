<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Interrupteur Chronopost (24/10/2026, demande de Ian) : en attendant les
 * identifiants Chronopost, la boutique ouvre en retrait chez les commerçants
 * partenaires uniquement. Activé par défaut ; à couper dans Paramètres > Expédition.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shipping.chronopost_enabled', true);
    }
};
