<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Settings\ShopSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi des notifications admin à l'adresse paramétrée en BO
 * (`admin_notification_email`) ; rien n'est envoyé tant qu'elle est vide.
 */
class AdminMailer
{
    public static function queue(Mailable $mailable): void
    {
        $to = app(ShopSettings::class)->admin_notification_email;

        if ($to) {
            Mail::to($to)->queue($mailable);
        }
    }
}
