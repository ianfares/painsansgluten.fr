<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Retours du 10/10/2026 (T27 L1) : l'étiquette « Expédié frais, à retirer en
 * relais » de la bannière devient un texte réglable dans l'admin. Valeur
 * initiale « Création à venir » (décision de Ian) ; vide = étiquette masquée.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('homepage.banner_badge_text', 'Création à venir');
    }

    public function down(): void
    {
        $this->migrator->delete('homepage.banner_badge_text');
    }
};
