<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte à la requête suivante un client dont le compte a été désactivé
 * (T27-L5). Ne touche que le guard « web » : la session admin éventuelle,
 * dans la même session, est conservée.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'Ce compte est désactivé. Contactez-nous via le formulaire de contact.';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();

        if ($user !== null && $user->isDeactivated()) {
            $guard->logout();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
