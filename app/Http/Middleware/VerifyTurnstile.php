<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Rules\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un jeton Cloudflare Turnstile valide sur les formulaires Fortify
 * exposés aux robots (création de compte, mot de passe oublié), sans
 * modifier Fortify lui-même. Le formulaire de contact le vérifie dans son
 * propre contrôleur.
 */
class VerifyTurnstile
{
    public const PROTECTED_ROUTES = ['register.store', 'password.email'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->routeIs(...self::PROTECTED_ROUTES)) {
            Validator::make(
                $request->only('cf-turnstile-response'),
                ['cf-turnstile-response' => ['required', new Turnstile]],
                ['cf-turnstile-response.required' => 'Merci de valider la vérification anti-robot.'],
            )->validate();
        }

        return $next($request);
    }
}
