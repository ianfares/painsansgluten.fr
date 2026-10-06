<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Settings\BankTransferSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Relance envoyée une seule fois, à mi-délai, si le virement n'est toujours
 * pas arrivé (PLAN.md §11, T15).
 */
class BankTransferReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Rappel — votre commande {$this->order->number} est en attente de virement");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bank-transfer-reminder',
            with: ['bankTransfer' => app(BankTransferSettings::class)],
        );
    }
}
