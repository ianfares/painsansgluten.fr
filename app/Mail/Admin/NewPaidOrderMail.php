<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Admin : une commande vient d'être payée (carte ou virement validé). */
class NewPaidOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nouvelle commande payée — {$this->order->number}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admin.new-paid-order');
    }
}
