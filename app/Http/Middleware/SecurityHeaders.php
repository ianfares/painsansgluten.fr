<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité sur toutes les réponses (T23, PLAN.md §20).
 * La CSP est en mode « report-only » : elle ne bloque rien, le navigateur
 * signale seulement en console ce qu'elle bloquerait (à durcir après GTM / T22).
 */
class SecurityHeaders
{
    private const CSP = [
        "default-src 'self'",
        // Livewire/Alpine évaluent des expressions : 'unsafe-eval' nécessaire.
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com https://www.googletagmanager.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: blob: https:",
        "connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://www.googletagmanager.com",
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
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', self::CSP));

        if ($request->isSecure() && ! app()->environment('local')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
