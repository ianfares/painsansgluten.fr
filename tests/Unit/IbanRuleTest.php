<?php

declare(strict_types=1);

use App\Rules\Iban;

function validateIban(string $value): array
{
    $errors = [];
    (new Iban)->validate('iban', $value, function (string $message) use (&$errors) {
        $errors[] = $message;
    });

    return $errors;
}

test('un IBAN français valide passe la validation', function () {
    expect(validateIban('FR1420041010050500013M02606'))->toBe([]);
});

test('un IBAN avec une somme de contrôle incorrecte est rejeté', function () {
    expect(validateIban('FR1420041010050500013M02607'))->not->toBe([]);
});

test('un IBAN au mauvais format est rejeté', function () {
    expect(validateIban('PAS-UN-IBAN'))->not->toBe([]);
});

test('les espaces dans un IBAN sont tolérés', function () {
    expect(validateIban('FR14 2004 1010 0505 0001 3M02 606'))->toBe([]);
});
