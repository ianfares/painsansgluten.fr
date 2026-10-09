<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ProAccountRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Accusé de réception envoyé au demandeur (T26 B1). */
class ProAccountRequestReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ProAccountRequest $proRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nous avons bien reçu votre demande de compte professionnel');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pro-request-received');
    }
}
