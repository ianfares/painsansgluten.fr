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

    /**
     * Horaires d'ouverture : [['day' => 'Monday', 'opens' => '08:00', 'closes' => '12:30'], …]
     * (une ligne par créneau ; jour en anglais, format schema.org). Pas de
     * type PHPDoc détaillé : la librairie de paramètres tenterait de convertir
     * chaque ligne et échouerait.
     */
    public array $opening_hours;

    /** Texte de présentation de la page « Professionnels » (HTML, purifié à l'affichage). */
    public ?string $pro_intro_html;

    /** @var list<string> Types d'activité proposés dans le formulaire de demande pro. */
    public array $pro_activity_types;

    public ?string $sender_email;

    public ?string $reply_to_email;

    public ?string $admin_notification_email;

    public static function group(): string
    {
        return 'shop';
    }
}
