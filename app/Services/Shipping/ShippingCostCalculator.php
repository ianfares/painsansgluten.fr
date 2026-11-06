<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Exceptions\Shipping\WeightOutOfRange;
use App\Models\ShippingRate;
use App\Settings\ShippingSettings;

/**
 * Calcul des frais de port (PLAN.md §8.3). Service unique : ne jamais
 * recalculer le port ailleurs dans le code (panier, tunnel, back-office).
 *
 * Deux modes :
 * - par défaut, tranche de poids total d'expédition (grille `shipping_rates`) ;
 * - si « paliers par montant » est activé (06/11/2026, offre de lancement
 *   d'Angélique), prix fixé par le montant des produits. La grille au poids
 *   reste alors le garde-fou du poids maximum d'un colis Chronopost.
 */
class ShippingCostCalculator
{
    public function __construct(private readonly ShippingSettings $settings) {}

    /**
     * @param  int  $grams  poids total du colis (produits + cadeau + emballage)
     * @param  int  $subtotalTtc  produits en centimes TTC, après remise, hors port
     * @return int Frais de port TTC en centimes (0 si franco atteint).
     *
     * @throws ShippingNotConfigured si la grille `shipping_rates` est vide.
     * @throws WeightOutOfRange si le poids ne correspond à aucune tranche
     *                          (y compris en mode paliers : colis trop lourd).
     */
    public function forWeight(int $grams, int $subtotalTtc): int
    {
        if ($this->freeShippingReached($subtotalTtc)) {
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

        $tier = $this->currentTier($subtotalTtc);
        if ($tier !== null) {
            return $tier['price_ttc'];
        }

        return $rate->priceTtc((float) ($this->settings->shipping_vat_rate ?? 0));
    }

    /**
     * Palier suivant, moins cher, pour le message « Plus que X € » du panier.
     *
     * @param  int  $subtotalTtc  produits en centimes TTC, après remise, hors port
     * @return array{missing_ttc: int, price_ttc: int}|null null si les paliers
     *                                                      sont désactivés, si le franco est atteint ou s'il n'y a pas de palier
     *                                                      moins cher au-dessus.
     */
    public function nextAmountTier(int $subtotalTtc): ?array
    {
        $current = $this->currentTier($subtotalTtc);
        if ($current === null || $this->freeShippingReached($subtotalTtc)) {
            return null;
        }

        foreach ($this->tiers() as $tier) {
            if ($tier['min_subtotal_ttc'] > $subtotalTtc && $tier['price_ttc'] < $current['price_ttc']) {
                return ['missing_ttc' => $tier['min_subtotal_ttc'] - $subtotalTtc, 'price_ttc' => $tier['price_ttc']];
            }
        }

        return null;
    }

    private function freeShippingReached(int $subtotalTtc): bool
    {
        return $this->settings->free_shipping_enabled
            && $this->settings->free_shipping_threshold_ttc !== null
            && $subtotalTtc >= $this->settings->free_shipping_threshold_ttc;
    }

    /**
     * Palier applicable : celui au seuil le plus haut atteint. null si le mode
     * est désactivé ou si aucun palier ne couvre le montant (grille mal
     * saisie) : on retombe alors sur la grille au poids.
     *
     * @return array{min_subtotal_ttc: int, price_ttc: int}|null
     */
    private function currentTier(int $subtotalTtc): ?array
    {
        if (! $this->settings->amount_tiers_enabled) {
            return null;
        }

        $match = null;
        foreach ($this->tiers() as $tier) {
            if ($tier['min_subtotal_ttc'] <= $subtotalTtc) {
                $match = $tier;
            }
        }

        return $match;
    }

    /**
     * Paliers triés par seuil croissant, montants forcés en entiers.
     *
     * @return list<array{min_subtotal_ttc: int, price_ttc: int}>
     */
    private function tiers(): array
    {
        $tiers = array_map(fn (array $tier): array => [
            'min_subtotal_ttc' => (int) $tier['min_subtotal_ttc'],
            'price_ttc' => (int) $tier['price_ttc'],
        ], $this->settings->amount_tiers);

        usort($tiers, fn (array $a, array $b): int => $a['min_subtotal_ttc'] <=> $b['min_subtotal_ttc']);

        return $tiers;
    }
}
