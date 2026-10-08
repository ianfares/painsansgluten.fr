<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\Mail\InvoiceLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Paiement carte confirmé par Stripe (PLAN.md §15). */
class OrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order, public readonly ?Invoice $invoice = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Commande {$this->order->number} confirmée — merci !");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order-confirmed', with: ['invoiceUrl' => $this->invoice ? InvoiceLink::for($this->invoice) : null]);
    }
}
