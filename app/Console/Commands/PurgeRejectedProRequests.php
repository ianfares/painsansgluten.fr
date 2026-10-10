<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProRequestStatus;
use App\Models\ProAccountRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Supprime les demandes de compte pro REFUSÉES depuis plus de
 * ProAccountRequest::RETENTION_MONTHS mois (RGPD : ne pas conserver de données
 * personnelles sans raison). Seul le statut « refusé » est concerné : les
 * demandes en attente ou acceptées ne sont jamais touchées. Planifiée
 * chaque jour (routes/console.php). Seul le nombre supprimé est journalisé.
 */
#[Signature('pro-requests:purge-rejected')]
#[Description('Supprime les demandes pro refusées depuis plus de 3 mois')]
class PurgeRejectedProRequests extends Command
{
    public function handle(): int
    {
        $count = ProAccountRequest::query()
            ->where('status', ProRequestStatus::Rejected)
            ->where('processed_at', '<', now()->subMonths(ProAccountRequest::RETENTION_MONTHS))
            ->delete();

        Log::info("pro-requests:purge-rejected : {$count} demande(s) supprimée(s).");
        $this->components->info("{$count} demande(s) pro refusée(s) supprimée(s).");

        return self::SUCCESS;
    }
}
