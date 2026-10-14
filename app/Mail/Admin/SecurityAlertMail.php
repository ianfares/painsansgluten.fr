<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use App\Settings\ShopSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte de sécurité envoyée à `security.alert_email` (support@yafar.org).
 * Objet : « {nom du site} — ALERTE — {quoi} » ; le corps détaille ce qui a été détecté.
 */
class SecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, list<string>|string>  $details  titre de section => lignes (ou texte)
     */
    public function __construct(
        public readonly string $topic,
        public readonly string $intro,
        public readonly array $details,
        public readonly string $advice,
    ) {}

    public static function siteName(): string
    {
        return app(ShopSettings::class)->shop_name ?: (string) config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::siteName().' — ALERTE — '.$this->topic);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admin.security-alert', with: [
            'siteName' => self::siteName(),
            'siteUrl' => (string) config('app.url'),
            'detectedAt' => now('Europe/Paris')->translatedFormat('l j F Y à H:i'),
        ]);
    }
}
