<?php

declare(strict_types=1);

use App\Http\Middleware\HandleLegacyRedirects;
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
