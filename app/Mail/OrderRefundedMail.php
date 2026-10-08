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

/** Commande remboursée + lien vers l'avoir (PLAN.md §15). */
class OrderRefundedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order, public readonly ?Invoice $creditNote = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Remboursement de votre commande {$this->order->number}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order-refunded', with: ['creditNoteUrl' => $this->creditNote ? InvoiceLink::for($this->creditNote) : null]);
    }
}
