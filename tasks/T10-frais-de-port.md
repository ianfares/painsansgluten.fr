# T10 — Frais de port
**Dépend de** : T04  **Branche** : `feature/T10-shipping-rates`  **Réf.** : PLAN §8.3

## À faire
- `ShippingCostCalculator::forWeight(int $grams, int $subtotalTtc): int` (centimes).
- Tranches `shipping_rates`, franco optionnel, TVA du port (paramètre).
- Grille vide → exception `ShippingNotConfigured` ; poids hors grille → `WeightOutOfRange`.
- Intégration dans le panier (T08).

## ⚠️ Avertissements
- Jamais 0 € par défaut si la grille est vide : blocage.

## Tests
Chaque tranche, bornes min/max, franco atteint / non atteint / désactivé, grille vide, hors grille.
