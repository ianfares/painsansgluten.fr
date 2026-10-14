<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Security\FileIntegrity;
use Illuminate\Console\Command;

/**
 * Photographie (SHA-256) du code sain : lancé à chaque déploiement, ou
 * après une modification volontaire sur le serveur (ex. `.env`).
 */
class IntegritySnapshotCommand extends Command
{
    protected $signature = 'security:integrity-snapshot';

    protected $description = 'Enregistre les empreintes SHA-256 du code (référence de la détection d\'intrusion)';

    public function handle(FileIntegrity $integrity): int
    {
        $this->info($integrity->snapshot().' fichiers enregistrés comme référence.');

        return self::SUCCESS;
    }
}
