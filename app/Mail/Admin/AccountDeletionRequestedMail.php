<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin : un client demande la suppression de son compte (RGPD). */
class AccountDeletionRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Demande de suppression de compte client');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admin.account-deletion-requested');
    }
}
