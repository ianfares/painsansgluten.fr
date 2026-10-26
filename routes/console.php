<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purge des paniers invités > 30 jours (PLAN.md §7, T08).
Schedule::command('cart:purge-old-guests')->daily();

// Purge RGPD des demandes de compte pro refusées depuis plus de 3 mois (T27-L3).
Schedule::command('pro-requests:purge-rejected')->daily();

// Relance/annulation des virements en attente (PLAN.md §11, T15).
Schedule::command('orders:process-bank-transfer-deadlines')->hourly();

// File d'attente (factures, emails) traitée par le cron, sans superviseur :
// le plus simple sur un seul serveur (voir docs/DECISIONS.md, T14).
// Verrou anti-chevauchement limité à 5 min (audit du 26/10/2026) : par défaut il dure
// 24 h, et un processus tué (OOM, redémarrage) bloquait emails et factures toute une journée.
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping(5);

// Détection d'intrusion : code comparé à la référence du déploiement, email à support@yafar.org si différence (14/10/2026).
Schedule::command('security:integrity-check')->hourly()->withoutOverlapping();
