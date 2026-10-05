<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Valeurs initiales de tous les groupes de paramètres (T04, PLAN.md §8, §11,
 * §12, §15, §16.3). Uniquement `null`/vide, sauf les deux valeurs
 * explicitement validées par la cliente (CLAUDE.md §2 : délai d'annulation
 * virement = 5 jours, quantité max par ligne = 20) — jamais d'autre valeur
 * métier inventée (CLAUDE.md §3.1).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shop.shop_name', null);
        $this->migrator->add('shop.contact_email', null);
        $this->migrator->add('shop.contact_phone', null);
        $this->migrator->add('shop.address_line1', null);
        $this->migrator->add('shop.postal_code', null);
        $this->migrator->add('shop.city', null);
        $this->migrator->add('shop.facebook_url', null);
        $this->migrator->add('shop.sender_email', null);
        $this->migrator->add('shop.reply_to_email', null);
        $this->migrator->add('shop.admin_notification_email', null);

        $this->migrator->add('billing.company_name', null);
        $this->migrator->add('billing.legal_form', null);
        $this->migrator->add('billing.share_capital', null);
        $this->migrator->add('billing.address', null);
        $this->migrator->add('billing.siret', null);
        $this->migrator->add('billing.rcs', null);
        $this->migrator->add('billing.vat_number', null);
        $this->migrator->add('billing.invoice_footer_mentions', null);
        $this->migrator->add('billing.invoice_number_format', null);
        $this->migrator->add('billing.credit_note_number_format', null);

        $this->migrator->add('bank_transfer.account_holder', null);
        $this->migrator->add('bank_transfer.iban', null);
        $this->migrator->add('bank_transfer.bic', null);
        $this->migrator->add('bank_transfer.bank_name', null);
        $this->migrator->add('bank_transfer.cancel_after_days', 5);
        $this->migrator->add('bank_transfer.auto_cancel_enabled', true);

        $this->migrator->add('shipping.shipping_weekdays', []);
        $this->migrator->add('shipping.order_cutoff_time', null);
        $this->migrator->add('shipping.production_lead_days', null);
        $this->migrator->add('shipping.tracking_url_template', null);
        $this->migrator->add('shipping.chronopost_product_code', null);
        $this->migrator->add('shipping.max_quantity_per_line', 20);
        $this->migrator->add('shipping.shipping_block_text', null);
        $this->migrator->add('shipping.non_shippable_message', 'Disponible uniquement sur nos marchés.');
        $this->migrator->add('shipping.relay_pickup_message', 'Produit frais — à retirer le jour même de sa mise à disposition en point relais.');
        $this->migrator->add('shipping.free_shipping_enabled', false);
        $this->migrator->add('shipping.free_shipping_threshold_ttc', null);
        $this->migrator->add('shipping.shipping_vat_rate', null);

        $this->migrator->add('homepage.logo_path', null);
        $this->migrator->add('homepage.favicon_path', null);
        $this->migrator->add('homepage.announcement_active', false);
        $this->migrator->add('homepage.announcement_text', null);
        $this->migrator->add('homepage.banner_image_path', null);
        $this->migrator->add('homepage.banner_title', null);
        $this->migrator->add('homepage.banner_subtitle', null);
        $this->migrator->add('homepage.banner_button_text', null);
        $this->migrator->add('homepage.banner_button_url', null);
        $this->migrator->add('homepage.featured_product_ids', []);
        $this->migrator->add('homepage.presentation_text', null);

        $this->migrator->add('seo.default_title', null);
        $this->migrator->add('seo.default_description', null);
        $this->migrator->add('seo.default_og_image_path', null);
    }
};
