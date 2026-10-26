<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * Levée quand la recherche de points relais Chronopost échoue (timeout,
 * réseau, identifiants refusés, réponse illisible). Le tunnel affiche un
 * message générique au client ; le détail technique est déjà journalisé.
 */
class RelayServiceUnavailable extends RuntimeException {}
