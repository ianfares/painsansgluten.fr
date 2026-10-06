<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Settings\BankTransferSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoyée juste après la création d'une commande par virement (PLAN.md §11) :
 * montant, coordonnées bancaires, référence obligatoire, échéance,
 * avertissement fabrication/virement instantané.
 */
class BankTransferInstructionsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre commande {$this->order->number} — instructions de virement");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bank-transfer-instructions',
            with: ['bankTransfer' => app(BankTransferSettings::class)],
        );
    }
}
