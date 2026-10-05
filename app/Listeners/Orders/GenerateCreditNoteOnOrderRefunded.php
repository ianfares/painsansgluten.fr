<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Actions\Invoicing\IssueCreditNoteAction;
use App\Enums\InvoiceType;
use App\Events\Orders\OrderRefunded;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateCreditNoteOnOrderRefunded implements ShouldQueue
{
    public function handle(OrderRefunded $event): void
    {
        if ($event->order->invoices()->where('type', InvoiceType::CreditNote)->exists()) {
            return;
        }

        app(IssueCreditNoteAction::class)->execute($event->order);
    }
}
