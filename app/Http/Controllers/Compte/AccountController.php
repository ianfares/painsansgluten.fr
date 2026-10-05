<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compte;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Espace client (PLAN.md §13). La mise à jour email/mot de passe passe
 * par les actions Fortify déjà en place (T03) : `user-profile-information.update`,
 * `user-password.update` — ce contrôleur ne gère que ce que Fortify ne
 * couvre pas (adresse, suppression de compte).
 */
class AccountController extends Controller
{
    public function dashboard(): View
    {
        $user = Auth::user();

        return view('compte.dashboard', [
            'recentOrders' => $user->orders()->latest()->limit(3)->get(),
        ]);
    }

    public function orders(): View
    {
        return view('compte.orders', [
            'orders' => Auth::user()->orders()->latest()->paginate(10),
        ]);
    }

    public function informations(): View
    {
        return view('compte.informations', [
            'address' => Auth::user()->addresses()->first(),
        ]);
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $address = $user->addresses()->firstOrNew(['type' => 'billing']);
        $address->fill([...$validated, 'country' => 'FR']);
        $user->addresses()->save($address);

        return back()->with('status', 'Adresse mise à jour.');
    }

    /**
     * Demande de suppression de compte : traitement **manuel** par un
     * admin (PLAN.md §13, §19) — jamais de suppression immédiate.
     */
    public function requestDeletion(): RedirectResponse
    {
        // `deletion_requested_at` n'est volontairement pas fillable (pas une
        // donnée que l'utilisateur doit pouvoir modifier par mass-assignment) :
        // affectation directe + save().
        $user = Auth::user();
        $user->deletion_requested_at = now();
        $user->save();

        return back()->with('status', 'Votre demande de suppression de compte a été enregistrée. Elle sera traitée par notre équipe.');
    }
}
