<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité sur toutes les réponses (T23, PLAN.md §20).
 * La CSP est BLOQUANTE depuis l'audit du 26/10/2026 (elle était en
 * « report-only ») : ressources réellement chargées en production vérifiées
 * (site public : domaine + Turnstile ; BO : polices fonts.bunny.net de Filament ;
 * GTM / GA4 après consentement). Tout nouveau service tiers (balise GTM vers
 * un autre domaine, widget…) doit être ajouté ici, sinon le navigateur le bloque.
 * 'unsafe-inline' et 'unsafe-eval' restent nécessaires (Livewire/Alpine,
 * Filament) : un nonce désactiverait 'unsafe-inline' et casserait les scripts
 * inline de Livewire (docs/DECISIONS.md, T22).
 */
class SecurityHeaders
{
    private const CSP = [
        "default-src 'self'",
        // Livewire/Alpine évaluent des expressions : 'unsafe-eval' nécessaire.
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com https://www.googletagmanager.com https://*.google-analytics.com",
        // fonts.bunny.net : police par défaut du back-office Filament.
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
        "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net",
        "img-src 'self' data: blob: https:",
        // Aperçus d'images du back-office (FilePond) : web worker créé depuis une URL blob:.
        "worker-src 'self' blob:",
        "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://stats.g.doubleclick.net",
        'frame-src https://challenges.cloudflare.com https://www.googletagmanager.com',
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self' https://checkout.stripe.com",
        "object-src 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Version de PHP non divulguée.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Content-Security-Policy', implode('; ', self::CSP));

        // Hors production (préprod…) : jamais indexé, même si un lien fuit (PLAN.md §17).
        if (! app()->environment('production')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure() && ! app()->environment('local')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
