<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Tout le catalogue est livrable : le badge « Livraison possible » est retiré
 * (Ian, 09/10/2026). Le bloc expédition n'y fait donc plus référence. Seul le
 * texte par défaut est modifié, jamais un texte retouché en BO.
 */
return new class extends SettingsMigration
{
    private const OLD_FIRST_SENTENCE = 'Livraison en point relais Chronopost disponible pour les produits portant la mention « Livraison possible », en France métropolitaine (hors Corse).';

    private const NEW_FIRST_SENTENCE = 'Livraison en point relais Chronopost, en France métropolitaine (hors Corse).';

    public function up(): void
    {
        $this->migrator->update('shipping.shipping_block_text', fn ($text) => is_string($text)
            ? str_replace(self::OLD_FIRST_SENTENCE, self::NEW_FIRST_SENTENCE, $text)
            : $text);
    }
};
