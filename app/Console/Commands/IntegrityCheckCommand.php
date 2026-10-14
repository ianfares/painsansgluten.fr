<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Admin\SecurityAlertMail;
use App\Services\Security\FileIntegrity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Vérification horaire : code modifié/ajouté/supprimé depuis le déploiement,
 * ou fichier exécutable dans un dossier d'upload → email d'alerte immédiat
 * (envoi direct, sans passer par la file d'attente).
 */
class IntegrityCheckCommand extends Command
{
    protected $signature = 'security:integrity-check';

    protected $description = 'Compare le code à la référence du déploiement et alerte par email en cas de différence';

    public function handle(FileIntegrity $integrity): int
    {
        $result = $integrity->check();

        if (! FileIntegrity::hasProblems($result)) {
            $this->info('Aucune anomalie.');

            return self::SUCCESS;
        }

        $details = [];
        if ($result['missing_manifest']) {
            $details['Référence introuvable ou illisible'] = 'Le fichier de référence des empreintes a disparu ou a été modifié : la comparaison est impossible.';
        }
        if ($result['suspicious'] !== []) {
            $details['Fichier(s) exécutable(s) dans un dossier d\'upload (porte dérobée probable) : '.count($result['suspicious'])] = $result['suspicious'];
        }
        if ($result['modified'] !== []) {
            $details['Fichier(s) modifié(s) : '.count($result['modified'])] = $result['modified'];
        }
        if ($result['added'] !== []) {
            $details['Fichier(s) ajouté(s) : '.count($result['added'])] = $result['added'];
        }
        if ($result['removed'] !== []) {
            $details['Fichier(s) supprimé(s) : '.count($result['removed'])] = $result['removed'];
        }

        $topic = $result['suspicious'] !== [] ? 'fichier suspect détecté' : 'fichiers du site modifiés';

        Mail::to((string) config('security.alert_email'))->send(new SecurityAlertMail(
            topic: $topic,
            intro: 'La vérification automatique (toutes les heures) a détecté des différences avec la version du site déployée.',
            details: $details,
            advice: 'Si personne n\'a modifié le site, il a peut-être été piraté : ne supprimez rien, prévenez le développeur. '
                .'Si c\'est une modification volontaire (ex. fichier .env), relancez « php artisan security:integrity-snapshot » sur le serveur pour mettre à jour la référence. '
                .'Cette alerte est renvoyée toutes les heures tant que la situation n\'est pas réglée.',
        ));

        $this->error('Anomalie détectée : alerte envoyée.');

        return self::FAILURE;
    }
}
