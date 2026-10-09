<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Retours du 09/10/2026 (T26 lot A). Les textes fournis par Ian ne remplacent
 * jamais une valeur déjà saisie en BO.
 */
return new class extends SettingsMigration
{
    private const SHIPPING_BLOCK_TEXT = "Livraison en point relais Chronopost disponible pour les produits portant la mention « Livraison possible », en France métropolitaine (hors Corse). Les frais de livraison sont calculés et indiqués lors de la commande.\n\nAfin de préserver au mieux la fraîcheur et la qualité de nos produits, nous vous recommandons de retirer votre colis le jour même de sa mise à disposition au point relais.\n\nLes produits étant alimentaires et périssables, Mon Sans Gluten by Angélique ne peut garantir leurs conditions de conservation en cas de retrait tardif du colis.";

    public function up(): void
    {
        // A8 : laboratoire sans accueil du public → pas d'horaires d'ouverture.
        $this->migrator->delete('shop.opening_hours');
        $this->migrator->add('shop.address_note', 'Laboratoire — pas d\'accueil du public');

        // A7 : libellé transporteur ; TVA du port 20 % par défaut (À CONFIRMER par le comptable).
        $this->migrator->add('shipping.carrier_label', 'Chronopost — Point relais (Chrono Relais 13)');
        $this->migrator->update('shipping.shipping_vat_rate', fn ($rate) => $rate ?? 20.0);

        // A5 : texte du bloc « Expédition et livraison ».
        $this->migrator->update('shipping.shipping_block_text', fn ($text) => filled($text) ? $text : self::SHIPPING_BLOCK_TEXT);

        // A9 : slogan officiel.
        $this->migrator->add('homepage.slogan', 'Le gluten s\'efface, le goût reste.');
    }
};
