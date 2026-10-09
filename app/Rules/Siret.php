<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SIRET : 14 chiffres et clé de Luhn valide. Contrôle de format uniquement
 * (l'existence de l'établissement n'est pas vérifiée). Exception connue des
 * établissements de La Poste (SIREN 356000000), dont les SIRET ne respectent
 * pas Luhn : volontairement ignorée (décision Ian, T26 B1).
 */
class Siret implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{14}$/', $value) || ! self::luhn($value)) {
            $fail('Ce numéro SIRET n\'est pas valide (14 chiffres).');
        }
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $char) {
            $n = (int) $char;
            if ($i % 2 === 1) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
        }

        return $sum % 10 === 0;
    }
}
