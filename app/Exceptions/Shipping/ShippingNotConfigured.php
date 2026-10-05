<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * Levée quand la grille de frais de port (`shipping_rates`) est vide.
 * Ne jamais intercepter pour appliquer un port à 0 € par défaut
 * (CLAUDE.md §4, PLAN.md §8.3) : le tunnel doit rester bloqué.
 */
class ShippingNotConfigured extends RuntimeException {}
