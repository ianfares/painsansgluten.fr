<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\BankTransferCancelledMail;
use App\Mail\BankTransferReminderMail;
use App\Models\Order;
use App\Services\Orders\OrderStateMachine;
use App\Settings\BankTransferSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Tâche planifiée horaire (PLAN.md §11, T15) : relance unique à mi-délai
 * pour les commandes par virement en attente, puis annulation automatique
 * à échéance si l'option est active (`BankTransferSettings::$auto_cancel_enabled`).
 */
#[Signature('orders:process-bank-transfer-deadlines')]
#[Description('Relance et annule automatiquement les commandes par virement en attente de paiement')]
class ProcessBankTransferDeadlinesCommand extends Command
{
    public function handle(BankTransferSettings $settings, OrderStateMachine $orderStateMachine): int
    {
        $cancelAfterDays = $settings->cancel_after_days;
        $reminderThreshold = now()->subDays((int) ($cancelAfterDays / 2));

        $pending = Order::query()
            ->where('payment_method', PaymentMethod::BankTransfer)
            ->where('status', OrderStatus::PendingPayment)
            ->get();

        foreach ($pending as $order) {
            if ($order->bank_transfer_reminder_sent_at === null && $order->created_at->lessThanOrEqualTo($reminderThreshold)) {
                Mail::to($order->email)->queue(new BankTransferReminderMail($order));
                $order->bank_transfer_reminder_sent_at = now();
                $order->save();
            }

            if ($settings->auto_cancel_enabled && $order->created_at->lessThanOrEqualTo(now()->subDays($cancelAfterDays))) {
                $orderStateMachine->transition(
                    $order,
                    OrderStatus::Cancelled,
                    actorType: 'system',
                    comment: "Virement non reçu sous {$cancelAfterDays} jours.",
                );
                Mail::to($order->email)->queue(new BankTransferCancelledMail($order));
            }
        }

        return self::SUCCESS;
    }
}
