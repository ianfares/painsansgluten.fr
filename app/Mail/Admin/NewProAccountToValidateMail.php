<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin : un client vient de s'inscrire en « professionnel » (T27-L5). */
class NewProAccountToValidateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouveau compte pro à valider');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admin.new-pro-account-to-validate');
    }
}
