<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin : nouvelle commande en attente de virement. */
class NewBankTransferOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nouveau virement en attente — {$this->order->number}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admin.new-bank-transfer-order');
    }
}
