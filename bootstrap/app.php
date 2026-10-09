<?php

declare(strict_types=1);

use App\Http\Middleware\HandleLegacyRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Redirections 301 Shopify (PLAN.md §23, T20) : doit s'exécuter avant
        // la résolution de route pour couvrir les anciennes URLs qui ne
        // correspondent à aucune route actuelle.
        $middleware->web(prepend: [HandleLegacyRedirects::class]);
        // Anti-robot sur la création de compte et le mot de passe oublié.
        $middleware->web(append: [VerifyTurnstile::class]);

        // En-têtes de sécurité sur toutes les réponses (T23). La vraie IP des
        // visiteurs derrière Cloudflare est fournie par Apache (mod_remoteip) :
        // pas de « trusted proxies » côté Laravel (voir docs/DECISIONS.md).
        $middleware->append(SecurityHeaders::class);

        // Stripe signe ses webhooks : pas de jeton CSRF possible (T14).
        $middleware->validateCsrfTokens(except: ['webhooks/stripe']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
