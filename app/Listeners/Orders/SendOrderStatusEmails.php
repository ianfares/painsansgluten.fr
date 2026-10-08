<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderShipped;
use App\Mail\Admin\NewPaidOrderMail;
use App\Mail\OrderShippedMail;
use App\Services\Mail\AdminMailer;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Mail;

/**
 * Emails liés aux changements de statut qui ne dépendent pas d'un document
 * (PLAN.md §15, T19). Les confirmations avec facture/avoir partent depuis
 * GenerateInvoiceOnOrderPaid / GenerateCreditNoteOnOrderRefunded.
 * Exécuté après la validation de la transaction : jamais d'email pour un
 * changement de statut annulé.
 */
class SendOrderStatusEmails implements ShouldHandleEventsAfterCommit
{
    public function handleOrderPaid(OrderPaid $event): void
    {
        AdminMailer::queue(new NewPaidOrderMail($event->order));
    }

    public function handleOrderShipped(OrderShipped $event): void
    {
        Mail::to($event->order->email)->queue(new OrderShippedMail($event->order));
    }
}
