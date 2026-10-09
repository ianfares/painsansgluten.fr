<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Paramètres boutique (PLAN.md §15, §16.3). Valeurs nulles tant qu'elles
 * n'ont pas été renseignées par la cliente/Ian (CLAUDE.md §3.1 : jamais de
 * valeur métier inventée).
 */
class ShopSettings extends Settings
{
    public ?string $shop_name;

    public ?string $contact_email;

    public ?string $contact_phone;

    public ?string $address_line1;

    public ?string $postal_code;

    public ?string $city;

    public ?string $facebook_url;

    public ?string $instagram_url;

    /** Lien public de la fiche Google Business Profile. */
    public ?string $google_business_url;

    /** Mention affichée sous l'adresse (T26 A8), ex. « Laboratoire — pas d'accueil du public ». */
    public ?string $address_note;

    public ?string $sender_email;

    public ?string $reply_to_email;

    public ?string $admin_notification_email;

    public static function group(): string
    {
        return 'shop';
    }
}
