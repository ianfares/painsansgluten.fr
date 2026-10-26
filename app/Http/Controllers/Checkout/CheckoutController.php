<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Payments\PaymentAlreadyCompleted;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\StripeCheckoutService;
use App\Settings\BankTransferSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
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
    /** Durée max de création d'une session Stripe (appel API) + marge. */
    private const SESSION_LOCK_SECONDS = 30;

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

        // Deux ouvertures simultanées du lien (double clic, deux onglets) ne doivent pas créer
        // deux sessions Stripe payables en parallèle (audit du 26/10/2026) : verrou par commande.
        $lock = Cache::lock("stripe-session:order:{$order->id}", self::SESSION_LOCK_SECONDS);
        if (! $lock->get()) {
            return redirect()->route('checkout.confirmation', $order)
                ->with('payment_error', 'Votre paiement est déjà en cours de préparation : patientez quelques secondes puis réessayez.');
        }

        try {
            return redirect()->away($stripe->createSession($order));
        } catch (PaymentAlreadyCompleted) {
            // Paiement déjà fait sur une session précédente : la page de confirmation attend le webhook.
            return redirect()->route('checkout.confirmation', $order);
        } catch (Throwable $e) {
            Log::error('Stripe : création de la session de paiement impossible.', ['order' => $order->number, 'error' => $e::class]);

            return redirect()->route('checkout.confirmation', $order)
                ->with('payment_error', 'Le paiement par carte est momentanément indisponible. Merci de réessayer dans quelques minutes.');
        } finally {
            $lock->release();
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
