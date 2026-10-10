<?php

declare(strict_types=1);

namespace App\Enums;

/** Type de compte client (T27-L5). */
enum AccountType: string
{
    case Individual = 'individual';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particulier',
            self::Pro => 'Professionnel',
        };
    }
}
