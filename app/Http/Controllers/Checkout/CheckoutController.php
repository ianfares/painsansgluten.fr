<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\StripeCheckoutService;
use App\Settings\BankTransferSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Tunnel de commande (PLAN.md §9.1, T13). Le formulaire lui-même est le
 * composant Livewire `CheckoutWizard` ; ce contrôleur ne fait que servir
 * les pages qui l'entourent (entrée, retour Stripe, confirmation).
 */
class CheckoutController extends Controller
{
    public function create(): View
    {
        return view('checkout.wizard');
    }

    /**
     * Envoie vers la page de paiement Stripe (T14). Sert aussi à relancer le
     * paiement d'une commande abandonnée ou refusée. Ne change jamais le
     * statut : seul le webhook confirme un paiement.
     */
    public function stripeStart(Order $order, StripeCheckoutService $stripe): RedirectResponse
    {
        $payable = $order->payment_method === PaymentMethod::Stripe
            && in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::PaymentFailed], true);

        if (! $payable) {
            return redirect()->route('checkout.confirmation', $order);
        }

        try {
            return redirect()->away($stripe->createSession($order));
        } catch (Throwable $e) {
            Log::error('Stripe : création de la session de paiement impossible.', ['order' => $order->number, 'error' => $e::class]);

            return redirect()->route('checkout.confirmation', $order)
                ->with('payment_error', 'Le paiement par carte est momentanément indisponible. Merci de réessayer dans quelques minutes.');
        }
    }

    public function confirmation(Order $order, BankTransferSettings $bankTransfer): View
    {
        return view('checkout.confirmation', [
            'order' => $order->load('items'),
            'bankTransfer' => $bankTransfer,
        ]);
    }
}
