<?php

declare(strict_types=1);

namespace App\Services\Consent;

/**
 * Décide si le bandeau de consentement (tarteaucitron) et Google Tag Manager
 * sont actifs (T22, PLAN.md §18).
 *
 * Règle unique : production ET identifiant GTM valide renseigné. Partout ailleurs
 * (local, tests, préprod) rien n'est chargé : pas de bandeau, pas de GTM, pas de
 * cookie tiers, et le bouton « Gérer mes préférences » est masqué.
 */
final class CookieConsent
{
    /** Format d'un identifiant de conteneur GTM, ex. GTM-KFK55VB8. */
    private const GTM_ID_PATTERN = '/^GTM-[A-Z0-9]{4,}$/';

    /** Valeur factice de .env.example : jamais considérée comme un vrai identifiant. */
    private const PLACEHOLDER_ID = 'GTM-XXXXXXX';

    /** Identifiant GTM utilisable, ou null s'il est absent ou mal formé. */
    public function gtmId(): ?string
    {
        $id = config('services.gtm.id');

        if (! is_string($id) || $id === self::PLACEHOLDER_ID || preg_match(self::GTM_ID_PATTERN, $id) !== 1) {
            return null;
        }

        return $id;
    }

    public function isActive(): bool
    {
        return app()->environment('production') && $this->gtmId() !== null;
    }
}
