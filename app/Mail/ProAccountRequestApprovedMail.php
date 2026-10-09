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
 * Envoyé au demandeur quand l'admin approuve sa demande : « nous revenons
 * vers vous » (T26 B1 ; la création du compte pro arrive en V2).
 */
class ProAccountRequestApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ProAccountRequest $proRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre demande de compte professionnel');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pro-request-approved');
    }
}
