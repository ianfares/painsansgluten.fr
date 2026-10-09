<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Paramètres d'expédition (PLAN.md §8.3, §8.4). `max_quantity_per_line`
 * (20) est la seule valeur par défaut explicitement validée (CLAUDE.md §2) ;
 * le reste reste vide tant que non renseigné — tant que `shipping_weekdays`,
 * `order_cutoff_time` ou `production_lead_days` sont vides, le tunnel de
 * commande doit rester bloqué (PLAN §8.4, à appliquer en T09/T13).
 */
class ShippingSettings extends Settings
{
    /** @var list<string> Jours d'expédition (ex. ["monday", "wednesday"]) */
    public array $shipping_weekdays;

    public ?string $order_cutoff_time;

    public ?int $production_lead_days;

    public ?string $tracking_url_template;

    public ?string $chronopost_product_code;

    /** Libellé du transporteur affiché au client (tunnel, Stripe) — T26 A7. */
    public ?string $carrier_label;

    public int $max_quantity_per_line;

    public ?string $shipping_block_text;

    public ?string $non_shippable_message;

    public ?string $relay_pickup_message;

    public bool $free_shipping_enabled;

    public ?int $free_shipping_threshold_ttc;

    public ?float $shipping_vat_rate;

    public static function group(): string
    {
        return 'shipping';
    }
}
