<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use App\Models\ProAccountRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin : une demande de compte professionnel vient d'être déposée. */
class NewProAccountRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ProAccountRequest $proRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouvelle demande de compte professionnel');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admin.new-pro-request');
    }
}
