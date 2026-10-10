<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Commande créée par la boutique : récapitulatif + lien de paiement par carte (T27-L10). */
class ManualOrderPaymentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre commande {$this->order->number} — finalisez le paiement");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.manual-order-payment-request', with: ['paymentUrl' => route('checkout.stripe.start', $this->order)]);
    }
}
