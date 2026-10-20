<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Pâtisserie offerte (20/10/2026) : désactivée par défaut ; seuil et poids à
 * renseigner dans Paramètres > Pâtisserie offerte avant activation.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('free_gift.enabled', false);
        $this->migrator->add('free_gift.threshold_ttc', 0);
        $this->migrator->add('free_gift.label', 'Pâtisserie offerte');
        $this->migrator->add('free_gift.weight_g', 0);
    }
};
