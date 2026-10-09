<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Settings\ShippingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Commande expédiée : suivi + rappel de retrait le jour même (PLAN.md §15). */
class OrderShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre commande {$this->order->number} est en route");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order-shipped', with: ['trackingUrl' => $this->order->trackingUrl(), 'pickupMessage' => app(ShippingSettings::class)->relay_pickup_message]);
    }
}
