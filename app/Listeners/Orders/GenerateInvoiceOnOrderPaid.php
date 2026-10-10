<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Actions\Invoicing\IssueInvoiceAction;
use App\Enums\PaymentMethod;
use App\Events\Orders\OrderPaid;
use App\Mail\BankTransferPaidMail;
use App\Mail\OrderConfirmedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Génère la facture à chaque commande payée (PLAN.md §12), en queue (le
 * webhook Stripe/la validation virement doivent répondre vite —
 * CLAUDE.md §4). **Idempotent** : une facture existe déjà par commande
 * (une seule transition pending→paid possible), jamais de doublon.
 */
class GenerateInvoiceOnOrderPaid implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        if ($event->order->invoices()->exists()) {
            return;
        }

        $invoice = app(IssueInvoiceAction::class)->execute($event->order);

        // Email de confirmation client envoyé ici, une fois la facture émise,
        // pour qu'il contienne son lien (PLAN.md §15, T19).
        $mail = $event->order->payment_method !== PaymentMethod::BankTransfer
            ? new OrderConfirmedMail($event->order, $invoice)
            : new BankTransferPaidMail($event->order, $invoice);
        Mail::to($event->order->email)->queue($mail);
    }
}
