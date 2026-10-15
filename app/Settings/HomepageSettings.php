<?php

declare(strict_types=1);

namespace App\Settings;

use Mews\Purifier\Facades\Purifier;
use Spatie\LaravelSettings\Settings;

/**
 * Accueil / Apparence (PLAN.md §16.3). Couleurs et polices restent figées
 * dans le code en V1 (non paramétrables) — seul le contenu l'est.
 */
class HomepageSettings extends Settings
{
    public ?string $logo_path;

    public ?string $favicon_path;

    public bool $announcement_active;

    public ?string $announcement_text;

    public ?string $banner_image_path;

    public ?string $banner_title;

    public ?string $banner_subtitle;

    public ?string $banner_button_text;

    public ?string $banner_button_url;

    /** Étiquette sur l'image de la bannière ; vide = étiquette masquée (retours 10/10/2026). */
    public ?string $banner_badge_text;

    /** @var list<int> IDs des produits mis en avant (voir App\Models\Product) */
    public array $featured_product_ids;

    public ?string $presentation_text;

    /** Slogan officiel affiché sur l'accueil (T26 A9). */
    public ?string $slogan;

    public static function group(): string
    {
        return 'homepage';
    }

    /**
     * Texte de présentation de l'accueil, mis en forme dans le BO (RichEditor) :
     * HTML purifié à l'affichage (liste blanche, QUALITE.md §2.3) ; un ancien
     * texte brut reste échappé avec ses retours à la ligne.
     */
    public function presentationHtml(): ?string
    {
        $text = $this->presentation_text;

        if ($text === null || trim(strip_tags($text)) === '') {
            return null;
        }

        return $text === strip_tags($text) ? nl2br(e($text)) : Purifier::clean($text);
    }
}
