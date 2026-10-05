<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide le format d'un IBAN (structure générale + somme de contrôle
 * mod-97, ISO 13616). T04 (PLAN.md) : "IBAN : validation de format."
 *
 * Validation triviale réalisée à la main plutôt que via un package
 * (QUALITE.md §2.14 : pas de dépendance pour une fonction triviale).
 */
class Iban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Le champ :attribute doit être une chaîne.');

            return;
        }

        $iban = strtoupper(str_replace(' ', '', $value));

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            $fail('Le champ :attribute n\'est pas un IBAN valide.');

            return;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        if ($this->mod97($numeric) !== 1) {
            $fail('Le champ :attribute n\'est pas un IBAN valide (somme de contrôle incorrecte).');
        }
    }

    /**
     * Calcule le modulo 97 d'une chaîne numérique potentiellement très
     * longue, par blocs (un IBAN dépasse la précision d'un int natif).
     */
    private function mod97(string $numeric): int
    {
        $remainder = $numeric;

        while (strlen($remainder) > 9) {
            $chunk = substr($remainder, 0, 9);
            $remainder = (string) ((int) $chunk % 97).substr($remainder, 9);
        }

        return (int) $remainder % 97;
    }
}
