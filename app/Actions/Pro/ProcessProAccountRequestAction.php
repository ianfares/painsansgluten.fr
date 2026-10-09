<?php

declare(strict_types=1);

namespace App\Actions\Pro;

use App\Enums\ProRequestStatus;
use App\Mail\ProAccountRequestApprovedMail;
use App\Mail\ProAccountRequestRejectedMail;
use App\Models\ProAccountRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Traite une demande de compte pro (T26 B1) : passe de « en attente » à
 * « approuvée » ou « refusée » et prévient le demandeur par email (en
 * file d'attente). La création du compte pro n'existe pas encore (V2).
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
        $this->process($request, ProRequestStatus::Approved, $comment);
        Mail::to($request->email)->queue(new ProAccountRequestApprovedMail($request));
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
