<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoyée quand une commande par virement est annulée automatiquement à
 * échéance, faute de virement reçu (PLAN.md §11, T15).
 */
class BankTransferCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre commande {$this->order->number} a été annulée");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.bank-transfer-cancelled');
    }
}
