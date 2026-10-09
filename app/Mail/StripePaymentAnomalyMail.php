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
 * Alerte admin : montant payé sur Stripe différent du total de la commande
 * (PLAN.md §10). La commande reste dans son statut, à vérifier à la main.
 */
class StripePaymentAnomalyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order, public readonly string $reason = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "⚠️ Anomalie de paiement — commande {$this->order->number}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.stripe-payment-anomaly');
    }
}
