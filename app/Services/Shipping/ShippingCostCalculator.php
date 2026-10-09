<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Exceptions\Shipping\WeightOutOfRange;
use App\Models\ShippingRate;
use App\Settings\ShippingSettings;

/**
 * Calcul des frais de port par tranche de poids total d'expédition
 * (PLAN.md §8.3). Service unique : ne jamais recalculer le port ailleurs
 * dans le code (panier, tunnel, back-office).
 */
class ShippingCostCalculator
{
    public function __construct(private readonly ShippingSettings $settings) {}

    /**
     * @return int Frais de port TTC en centimes (0 si franco atteint).
     *
     * @throws ShippingNotConfigured si la grille `shipping_rates` est vide.
     * @throws WeightOutOfRange si le poids ne correspond à aucune tranche.
     */
    public function forWeight(int $grams, int $subtotalTtc): int
    {
        if ($this->settings->free_shipping_enabled
            && $this->settings->free_shipping_threshold_ttc !== null
            && $subtotalTtc >= $this->settings->free_shipping_threshold_ttc
        ) {
            return 0;
        }

        if (ShippingRate::query()->count() === 0) {
            throw new ShippingNotConfigured('La grille de frais de port n\'est pas renseignée.');
        }

        $rate = ShippingRate::query()
            ->where('min_weight_g', '<=', $grams)
            ->where('max_weight_g', '>=', $grams)
            ->orderBy('min_weight_g')
            ->first();

        if (! $rate) {
            throw new WeightOutOfRange("Aucune tranche de frais de port ne couvre {$grams} g.");
        }

        return $rate->priceTtc((float) ($this->settings->shipping_vat_rate ?? 0));
    }
}
