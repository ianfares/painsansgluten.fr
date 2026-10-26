<?php

declare(strict_types=1);

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * Création ou annulation d'étiquette Chronopost impossible. Le message est
 * destiné à l'admin (français, sans détail technique ni donnée personnelle).
 */
class ShippingLabelFailed extends RuntimeException {}
