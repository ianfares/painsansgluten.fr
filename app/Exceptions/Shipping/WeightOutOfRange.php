<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * Levée quand le poids total d'expédition dépasse la grille de frais de
 * port renseignée. Le client doit voir un message "Contactez-nous"
 * (PLAN.md §8.3), jamais une commande silencieusement sans frais.
 */
class WeightOutOfRange extends RuntimeException {}
