<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ProAccountRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoyé au demandeur quand l'admin approuve sa demande : un seul email.
 * Nouveau compte : bouton « Choisir mon mot de passe » (jeton Fortify).
 * Compte existant : il est désormais pro, bouton « Me connecter ».
 */
class ProAccountRequestApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ProAccountRequest $proRequest, public readonly ?string $passwordToken = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte professionnel est prêt');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pro-request-approved', with: [
            'passwordUrl' => $this->passwordToken !== null
                ? route('password.reset', ['token' => $this->passwordToken, 'email' => $this->proRequest->email])
                : null,
            'validityMinutes' => (int) config('auth.passwords.users.expire'),
        ]);
    }
}
