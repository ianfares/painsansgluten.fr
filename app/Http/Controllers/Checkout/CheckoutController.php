<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Settings\BankTransferSettings;
use Illuminate\View\View;

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
     * ⚠️ Provisoire (T13) : l'intégration Stripe Checkout (création de
     * session, redirection réelle, webhook) arrive en T14. En attendant,
     * cette page confirme juste que la commande est enregistrée.
     */
    public function stripeStart(Order $order): View
    {
        return view('checkout.stripe-pending', ['order' => $order]);
    }

    public function confirmation(Order $order, BankTransferSettings $bankTransfer): View
    {
        return view('checkout.confirmation', [
            'order' => $order->load('items'),
            'bankTransfer' => $bankTransfer,
        ]);
    }
}
