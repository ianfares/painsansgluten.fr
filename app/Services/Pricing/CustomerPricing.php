<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\AccountType;
use App\Models\User;

/**
 * Remise client en % (T27-L6). Seule source du taux appliqué : il vient
 * toujours du compte en base, jamais du navigateur. La remise porte sur les
 * produits uniquement (jamais sur les frais de port) et se calcule par
 * unité, arrondie au centime le plus proche : le prix unitaire remisé est
 * donc un entier, identique dans le panier, la commande, Stripe et la facture.
 */
final class CustomerPricing
{
    public const MAX_PERCENT = 90;

    /** Taux applicable : 0 pour un visiteur, un compte désactivé ou un pro en attente de validation. */
    public static function percentFor(?User $user): int
    {
        if ($user === null || $user->isDeactivated()) {
            return 0;
        }

        if ($user->account_type === AccountType::Pro && ! $user->isApprovedPro()) {
            return 0;
        }

        return max(0, min(self::MAX_PERCENT, (int) $user->discount_percent));
    }

    /** Remise sur une unité, en centimes (arrondi au plus proche, demi vers le haut). */
    public static function unitDiscount(int $unitPriceTtc, int $percent): int
    {
        return $percent > 0 ? intdiv($unitPriceTtc * $percent + 50, 100) : 0;
    }

    /** Prix unitaire TTC après remise, en centimes. */
    public static function unitNet(int $unitPriceTtc, int $percent): int
    {
        return $unitPriceTtc - self::unitDiscount($unitPriceTtc, $percent);
    }
}
