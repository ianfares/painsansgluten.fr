<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Actions\Invoicing\IssueInvoiceAction;
use App\Events\Orders\OrderPaid;
use Illuminate\Contracts\Queue\ShouldQueue;

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

        app(IssueInvoiceAction::class)->execute($event->order);
    }
}
