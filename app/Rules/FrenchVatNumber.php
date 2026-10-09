<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * N° de TVA intracommunautaire français : « FR » + 2 caractères (clé) +
 * 9 chiffres (SIREN). Contrôle de format uniquement.
 */
class FrenchVatNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^FR[0-9A-Z]{2}\d{9}$/', $value)) {
            $fail('Ce numéro de TVA intracommunautaire n\'est pas valide (ex. FR12345678901).');
        }
    }
}
