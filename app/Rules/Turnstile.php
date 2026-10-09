<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie côté serveur le jeton Cloudflare Turnstile envoyé par le formulaire
 * (le widget seul ne protège de rien : seul ce contrôle compte).
 */
class Turnstile implements ValidationRule
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = 'La vérification anti-robot a échoué. Merci de réessayer.';

        if (! is_string($value) || $value === '') {
            $fail($message);

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => (string) config('services.turnstile.secret_key'),
                'response' => $value,
            ]);
            $valid = $response->json('success') === true;
        } catch (ConnectionException) {
            Log::warning('Turnstile : service de vérification injoignable.');
            $valid = false;
        }

        if (! $valid) {
            $fail($message);
        }
    }
}
