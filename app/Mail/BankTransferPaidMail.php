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
 * Envoyée à la cliente quand son virement a été vérifié et validé en BO
 * (PLAN.md §11, T15) : confirmation + nouvelle date d'expédition.
 */
class BankTransferPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Virement reçu — votre commande {$this->order->number} va être préparée");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.bank-transfer-paid');
    }
}
