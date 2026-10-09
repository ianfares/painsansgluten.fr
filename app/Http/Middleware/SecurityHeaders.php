<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité sur toutes les réponses (T23, PLAN.md §20).
 * La CSP est en mode « report-only » : elle ne bloque rien, le navigateur
 * signale seulement en console ce qu'elle bloquerait. T22 : GTM / GA4 autorisés ci-dessous ;
 * passage en mode bloquant à décider après observation en production (docs/DECISIONS.md).
 */
class SecurityHeaders
{
    private const CSP = [
        "default-src 'self'",
        // Livewire/Alpine évaluent des expressions : 'unsafe-eval' nécessaire.
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com https://www.googletagmanager.com https://*.google-analytics.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: blob: https:",
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
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', self::CSP));

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
