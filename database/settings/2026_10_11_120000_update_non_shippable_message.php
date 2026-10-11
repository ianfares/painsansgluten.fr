<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Produit non expédiable = commandable en retrait uniquement (décision client
 * 2026-10-11). Le texte d'origine est remplacé seulement s'il n'a pas été modifié.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('shipping.non_shippable_message', fn (?string $message) => $message === 'Disponible uniquement sur nos marchés.'
            ? 'Produit non expédié : disponible uniquement en retrait chez un commerçant partenaire (ou au laboratoire pour les professionnels autorisés).'
            : $message);
    }
};
