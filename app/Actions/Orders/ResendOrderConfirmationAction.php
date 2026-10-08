<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\BankTransferInstructionsMail;
use App\Mail\BankTransferPaidMail;
use App\Mail\OrderConfirmedMail;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * BO « Renvoyer l'email de confirmation » (T19) : renvoie le dernier email
 * utile au client selon l'état de la commande.
 */
class ResendOrderConfirmationAction
{
    /** Sans requête : appelé pour chaque ligne de la liste des commandes. */
    public static function isAvailableFor(Order $order): bool
    {
        return self::isPaid($order)
            || ($order->payment_method === PaymentMethod::BankTransfer && $order->status === OrderStatus::PendingPayment);
    }

    public function execute(Order $order): bool
    {
        $mail = self::mailFor($order);
        if (! $mail) {
            return false;
        }

        Mail::to($order->email)->queue($mail);

        return true;
    }

    private static function mailFor(Order $order): ?Mailable
    {
        $paid = self::isPaid($order);
        $invoice = $paid ? $order->invoices()->where('type', InvoiceType::Invoice)->first() : null;

        return match (true) {
            $order->payment_method === PaymentMethod::BankTransfer && $order->status === OrderStatus::PendingPayment => new BankTransferInstructionsMail($order),
            $paid && $order->payment_method === PaymentMethod::Stripe => new OrderConfirmedMail($order, $invoice),
            $paid => new BankTransferPaidMail($order, $invoice),
            default => null,
        };
    }

    private static function isPaid(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered], true);
    }
}
