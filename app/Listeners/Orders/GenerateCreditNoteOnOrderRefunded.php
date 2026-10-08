<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Actions\Invoicing\IssueCreditNoteAction;
use App\Enums\InvoiceType;
use App\Events\Orders\OrderRefunded;
use App\Mail\OrderRefundedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class GenerateCreditNoteOnOrderRefunded implements ShouldQueue
{
    public function handle(OrderRefunded $event): void
    {
        if ($event->order->invoices()->where('type', InvoiceType::CreditNote)->exists()) {
            return;
        }

        $creditNote = app(IssueCreditNoteAction::class)->execute($event->order);

        // Email client envoyé une fois l'avoir émis, pour qu'il contienne son lien (T19).
        Mail::to($event->order->email)->queue(new OrderRefundedMail($event->order, $creditNote));
    }
}
