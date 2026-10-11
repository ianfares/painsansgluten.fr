<?php

declare(strict_types=1);

namespace App\Actions\Pro;

use App\Actions\Customers\ManageCustomerAccount;
use App\Enums\AccountType;
use App\Enums\ProRequestStatus;
use App\Mail\ProAccountRequestApprovedMail;
use App\Mail\ProAccountRequestRejectedMail;
use App\Models\ProAccountRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Traite une demande de compte pro (T26 B1) : passe de « en attente » à
 * « approuvée » ou « refusée » et prévient le demandeur par email (en
 * file d'attente). Approuver crée le compte client pro validé (email pour
 * choisir le mot de passe), ou bascule en pro le compte qui a déjà cet email
 * (correctif du 11/10/2026 : l'approbation ne créait aucun compte).
 *
 * Verrou + contrôle du statut : un double clic ou deux admins simultanés ne
 * déclenchent qu'un seul traitement et un seul email.
 */
class ProcessProAccountRequestAction
{
    /**
     * @throws RuntimeException si la demande n'est plus en attente
     */
    public function approve(ProAccountRequest $request, ?string $comment = null): void
    {
        $accountCreated = DB::transaction(function () use ($request, $comment): bool {
            $this->process($request, ProRequestStatus::Approved, $comment);

            return $this->attachAccount($request);
        });

        Mail::to($request->email)->queue(new ProAccountRequestApprovedMail($request, $accountCreated));
    }

    /**
     * Rattrapage pour une demande approuvée avant ce correctif : crée (ou
     * bascule) le compte et renvoie l'email d'approbation.
     *
     * @throws RuntimeException si la demande n'est pas approuvée ou a déjà un compte
     */
    public function createAccountFor(ProAccountRequest $request): void
    {
        $accountCreated = DB::transaction(function () use ($request): bool {
            $locked = ProAccountRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== ProRequestStatus::Approved || $locked->user_id !== null) {
                throw new RuntimeException('Cette demande n\'est pas approuvée ou a déjà un compte client.');
            }

            return $this->attachAccount($request);
        });

        Mail::to($request->email)->queue(new ProAccountRequestApprovedMail($request, $accountCreated));
    }

    /**
     * Crée le compte pro validé (true) ou bascule en pro le compte existant
     * qui a le même email (false), et le rattache à la demande.
     */
    private function attachAccount(ProAccountRequest $request): bool
    {
        $accounts = app(ManageCustomerAccount::class);
        $user = User::query()->where('email', $request->email)->first();
        $created = $user === null;

        if ($user === null) {
            // Envoie aussi l'email « choisissez votre mot de passe ».
            $user = $accounts->create([
                'account_type' => AccountType::Pro->value,
                'first_name' => $request->contact_first_name,
                'last_name' => $request->contact_last_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'company_name' => $request->company_name,
                'siret' => $request->siret,
            ]);
        } else {
            $accounts->promoteToPro($user, $request->company_name, $request->siret);
        }

        $request->forceFill(['user_id' => $user->id])->save();

        return $created;
    }

    /**
     * @throws RuntimeException si la demande n'est plus en attente
     */
    public function reject(ProAccountRequest $request, ?string $comment = null): void
    {
        $this->process($request, ProRequestStatus::Rejected, $comment);
        Mail::to($request->email)->queue(new ProAccountRequestRejectedMail($request));
    }

    private function process(ProAccountRequest $request, ProRequestStatus $status, ?string $comment): void
    {
        DB::transaction(function () use ($request, $status, $comment): void {
            $locked = ProAccountRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== ProRequestStatus::Pending) {
                throw new RuntimeException('Cette demande a déjà été traitée.');
            }

            // Hors $fillable volontairement : seul ce service modifie le statut.
            $locked->forceFill([
                'status' => $status,
                'admin_comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
                'processed_at' => now(),
            ])->save();

            $request->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
