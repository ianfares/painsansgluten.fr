<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Mail\Admin\SecurityAlertMail;
use App\Models\Admin;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Connexion au back-office depuis une adresse IP jamais vue pour cet admin →
 * email d'alerte à support@yafar.org (demande de Ian, 14/10/2026).
 * En base, seule une empreinte HMAC de l'IP est gardée ; l'IP en clair ne
 * figure que dans l'email. Rien n'est écrit dans les logs.
 */
class AlertOnNewAdminIp
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof Admin) {
            return;
        }

        $request = request();
        $ip = (string) $request->ip();
        $ipHash = hash_hmac('sha256', $ip, (string) config('app.key'));
        $now = now();

        // insertOrIgnore + index unique : une seule alerte même si deux connexions arrivent en même temps.
        $isNew = DB::table('admin_known_ips')->insertOrIgnore([
            'admin_id' => $event->user->getKey(),
            'ip_hash' => $ipHash,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;

        if (! $isNew) {
            DB::table('admin_known_ips')
                ->where('admin_id', $event->user->getKey())
                ->where('ip_hash', $ipHash)
                ->update(['last_seen_at' => $now, 'updated_at' => $now]);

            return;
        }

        Mail::to((string) config('security.alert_email'))->queue(new SecurityAlertMail(
            topic: 'connexion admin depuis une nouvelle adresse IP',
            intro: 'Quelqu\'un vient de se connecter au back-office depuis une adresse IP jamais utilisée par ce compte.',
            details: [
                'Compte admin' => $event->user->name.' ('.$event->user->email.')',
                'Adresse IP' => $ip,
                'Navigateur' => Str::limit((string) $request->userAgent(), 200) ?: 'inconnu',
                'Case « Se souvenir de moi » cochée' => $event->remember ? 'oui' : 'non',
            ],
            advice: 'Si c\'est bien vous (nouveau lieu, box internet redémarrée, téléphone en 4G…), ignorez ce message. '
                .'Sinon, changez immédiatement le mot de passe de ce compte et prévenez le développeur.',
        ));
    }
}
