<?php

declare(strict_types=1);

namespace App\Exceptions\Orders;

use RuntimeException;

/** Commande manuelle ou validation par avoir refusée (T27-L10) ; message affiché à l'admin. */
class ManualOrderNotAllowed extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
