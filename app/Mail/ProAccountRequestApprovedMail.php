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
 * Envoyé au demandeur quand l'admin approuve sa demande. Nouveau compte :
 * un second email (lien pour choisir le mot de passe) arrive en parallèle.
 * Compte existant : il est désormais pro, il suffit de se connecter.
 */
class ProAccountRequestApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ProAccountRequest $proRequest, public readonly bool $accountCreated = true) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte professionnel est prêt');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pro-request-approved');
    }
}
