<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Référencement local (T21) : réseaux, fiche Google Business, horaires.
 * Vides par défaut : la cliente les renseigne en BO (CLAUDE.md §3.1).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shop.instagram_url', null);
        $this->migrator->add('shop.google_business_url', null);
        $this->migrator->add('shop.opening_hours', []);
    }
};
