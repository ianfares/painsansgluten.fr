<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Commande prête au retrait : adresse, horaires et instructions du point (T27-L7b). */
class OrderReadyForPickupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre commande {$this->order->number} est prête à être retirée");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order-ready-for-pickup', with: ['point' => $this->order->relay_snapshot ?? []]);
    }
}
