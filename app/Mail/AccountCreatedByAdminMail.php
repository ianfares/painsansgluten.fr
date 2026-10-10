<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Client créé à la main par l'admin : lien pour choisir son mot de passe (T27-L5). */
class AccountCreatedByAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user, public readonly string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte a été créé — choisissez votre mot de passe');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account-created-by-admin',
            with: [
                'resetUrl' => route('password.reset', ['token' => $this->token, 'email' => $this->user->email]),
                'validityMinutes' => (int) config('auth.passwords.users.expire'),
            ],
        );
    }
}
