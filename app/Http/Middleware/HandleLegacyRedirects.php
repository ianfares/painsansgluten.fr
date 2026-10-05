<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Redirect as RedirectModel;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirections 301 depuis l'ancien site Shopify (PLAN.md §16.4, §23).
 * Middleware global (exécuté avant la résolution de route) : une URL
 * Shopify qui ne correspond à aucune route actuelle doit quand même être
 * redirigée, pas afficher une 404.
 */
class HandleLegacyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.ltrim($request->path(), '/');

        // Règle générique (PLAN.md §23) : /products/{slug} → /produit/{slug},
        // query string (?variant=...) ignorée — non seedée en base (ce n'est
        // pas une redirection ligne à ligne, voir docs/DECISIONS.md T02/T20).
        if (preg_match('#^/products/([^/]+)$#', $path, $matches)) {
            return redirect("/produit/{$matches[1]}", 301);
        }

        $redirect = RedirectModel::query()
            ->where('source', $path)
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            return new RedirectResponse($redirect->target, $redirect->status_code);
        }

        return $next($request);
    }
}
