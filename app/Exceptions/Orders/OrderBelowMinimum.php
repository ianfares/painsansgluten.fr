<?php

declare(strict_types=1);

namespace App\Exceptions\Orders;

use RuntimeException;

/**
 * Levée quand le montant des produits n'atteint pas le minimum de commande
 * du mode de livraison choisi (Paramètres > Expédition, 19/10/2026).
 */
class OrderBelowMinimum extends RuntimeException
{
    public static function forAmounts(int $minimumTtc, int $missingTtc): self
    {
        return new self(sprintf(
            'Le minimum de commande pour ce mode de livraison est de %s € (hors frais de livraison) : il vous manque %s €.',
            number_format($minimumTtc / 100, 2, ',', ' '),
            number_format($missingTtc / 100, 2, ',', ' '),
        ));
    }
}
