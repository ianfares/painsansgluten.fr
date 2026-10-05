<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\ShippingRate;
use App\Settings\BankTransferSettings;
use App\Settings\BillingSettings;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;

/**
 * Point d'entrée unique pour savoir si la configuration métier est
 * complète (PLAN.md §27, CLAUDE.md : tant que des paramètres bloquants
 * manquent, le tunnel de commande doit rester bloqué — T09/T10/T13).
 *
 * Affiché en alerte sur le tableau de bord BO (T04) ; réutilisé tel quel
 * par les tâches suivantes plutôt que de dupliquer cette logique.
 */
class ConfigurationStatus
{
    /**
     * @return list<string> Messages d'alerte, vide si tout est renseigné.
     */
    public function missingItems(): array
    {
        $missing = [];

        $shop = app(ShopSettings::class);
        if (blank($shop->shop_name) || blank($shop->contact_email)) {
            $missing[] = 'Coordonnées de la boutique incomplètes (nom, email de contact).';
        }

        $billing = app(BillingSettings::class);
        if (blank($billing->siret) || blank($billing->vat_number)) {
            $missing[] = 'Mentions légales de facturation incomplètes (SIRET, n° TVA) — à valider par le comptable.';
        }

        $bankTransfer = app(BankTransferSettings::class);
        if (blank($bankTransfer->iban) || blank($bankTransfer->bic)) {
            $missing[] = 'Coordonnées bancaires (virement) non renseignées.';
        }

        $shipping = app(ShippingSettings::class);
        if ($shipping->shipping_weekdays === [] || blank($shipping->order_cutoff_time) || blank($shipping->production_lead_days)) {
            $missing[] = 'Paramètres de date d\'expédition incomplets (jours d\'expédition, heure limite, délai de fabrication).';
        }
        if (blank($shipping->chronopost_product_code)) {
            $missing[] = 'Code produit Chronopost non renseigné.';
        }
        if (blank($shipping->shipping_vat_rate)) {
            $missing[] = 'Taux de TVA des frais de port non renseigné — à valider par le comptable.';
        }

        if (ShippingRate::query()->count() === 0) {
            $missing[] = 'Grille de frais de port vide : le tunnel de commande est bloqué tant qu\'elle n\'est pas renseignée.';
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missingItems() === [];
    }
}
