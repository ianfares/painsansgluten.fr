<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compte;

use App\Enums\AccountType;
use App\Enums\ProStatus;
use App\Http\Controllers\Controller;
use App\Mail\Admin\AccountDeletionRequestedMail;
use App\Models\Invoice;
use App\Rules\Siret;
use App\Services\Mail\AdminMailer;
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

    /**
     * Détail d'une commande : recherchée parmi celles du client connecté
     * uniquement (jamais par identifiant global), sinon 404.
     */
    public function order(string $number): View
    {
        $order = Auth::user()->orders()->where('number', $number)->with(['items', 'invoices'])->firstOrFail();

        return view('compte.order', ['order' => $order]);
    }

    public function invoices(): View
    {
        $orderIds = Auth::user()->orders()->pluck('id');

        return view('compte.invoices', [
            'invoices' => Invoice::query()->whereIn('order_id', $orderIds)->latest('issued_at')->paginate(10),
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
     * Raison sociale / SIRET : modifiables uniquement tant que le compte pro
     * est en attente ; une fois validé, seul l'admin peut les changer
     * (sinon contournement de la validation).
     */
    public function updateCompany(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->account_type === AccountType::Pro && $user->pro_status === ProStatus::Pending, 403);

        $request->merge(['siret' => preg_replace('/[\s.]/', '', (string) $request->input('siret'))]);
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'siret' => ['required', new Siret],
        ]);

        $user->forceFill($validated)->save();

        return back()->with('status', 'Informations de l\'entreprise mises à jour.');
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

        AdminMailer::queue(new AccountDeletionRequestedMail($user));

        return back()->with('status', 'Votre demande de suppression de compte a été enregistrée. Elle sera traitée par notre équipe.');
    }
}
