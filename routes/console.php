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
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
