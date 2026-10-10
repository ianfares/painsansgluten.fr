<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\AccountType;
use App\Enums\ProStatus;
use App\Mail\AccountCreatedByAdminMail;
use App\Mail\ProAccountValidatedMail;
use App\Models\User;
use App\Services\Pricing\CustomerPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Gestion des comptes clients par l'admin (T27-L5) : création manuelle,
 * édition, validation pro, désactivation / réactivation. Seul point d'entrée
 * qui écrit les champs sensibles de `users` (type, statut pro, retrait labo,
 * désactivation), tous hors `$fillable`.
 */
class ManageCustomerAccount
{
    /**
     * Crée un client à la main. Aucun mot de passe saisi : mot de passe
     * aléatoire inutilisable + email de choix du mot de passe (lien de
     * réinitialisation Fortify). Un pro créé par l'admin est validé d'office.
     *
     * @param  array{account_type: string, first_name: string, last_name: string, phone: string, email: string, company_name?: ?string, siret?: ?string, discount_percent?: int|string|null}  $data
     */
    public function create(array $data): User
    {
        $isPro = $data['account_type'] === AccountType::Pro->value;

        $user = new User;
        $user->forceFill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(64)),
            'email_verified_at' => now(),
            'account_type' => $isPro ? AccountType::Pro : AccountType::Individual,
            'company_name' => $isPro ? ($data['company_name'] ?? null) : null,
            'siret' => $isPro ? ($data['siret'] ?? null) : null,
            'pro_status' => $isPro ? ProStatus::Approved : null,
            'pro_approved_at' => $isPro ? now() : null,
            'discount_percent' => self::discount($data['discount_percent'] ?? 0),
        ])->save();

        Password::broker()->sendResetLink(
            ['email' => $user->email],
            fn (User $u, string $token) => Mail::to($u->email)->queue(new AccountCreatedByAdminMail($u, $token)),
        );

        return $user;
    }

    /**
     * Édite un client (jamais email ni mot de passe). Passer en pro depuis
     * l'admin = validé d'office ; repasser en particulier efface le statut
     * pro et le retrait labo.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $isPro = ($data['account_type'] ?? $user->account_type->value) === AccountType::Pro->value;

        $user->forceFill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'account_type' => $isPro ? AccountType::Pro : AccountType::Individual,
            'company_name' => $isPro ? ($data['company_name'] ?? null) : null,
            'siret' => $isPro ? ($data['siret'] ?? null) : null,
            'discount_percent' => self::discount($data['discount_percent'] ?? $user->discount_percent),
        ]);

        if (! $isPro) {
            $user->forceFill(['pro_status' => null, 'pro_approved_at' => null, 'lab_pickup_allowed' => false]);
        } elseif ($user->pro_status === null) {
            $user->forceFill(['pro_status' => ProStatus::Approved, 'pro_approved_at' => now()]);
        }

        // Le retrait au labo n'a de sens que pour un pro validé.
        $user->lab_pickup_allowed = $user->isApprovedPro() && (bool) ($data['lab_pickup_allowed'] ?? false);
        $user->save();

        return $user;
    }

    /** Valide un compte pro en attente et prévient le client. */
    public function approvePro(User $user): void
    {
        if ($user->account_type !== AccountType::Pro || $user->pro_status !== ProStatus::Pending) {
            return;
        }

        $user->forceFill(['pro_status' => ProStatus::Approved, 'pro_approved_at' => now()])->save();

        Mail::to($user->email)->queue(new ProAccountValidatedMail($user));
    }

    /**
     * Désactive le compte : connexion bloquée, sessions coupées, demande de
     * suppression close. Aucune donnée n'est effacée (commandes, factures).
     */
    public function deactivate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'deactivated_at' => now(),
                'deletion_requested_at' => null,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    public function reactivate(User $user): void
    {
        $user->forceFill(['deactivated_at' => null])->save();
    }

    /** Taux de remise borné (T27-L6) : jamais négatif ni au-delà du plafond. */
    private static function discount(mixed $value): int
    {
        return max(0, min(CustomerPricing::MAX_PERCENT, (int) $value));
    }
}
