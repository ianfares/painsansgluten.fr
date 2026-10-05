<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PaymentMethod;
use App\Services\Cart\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Tunnel de commande (PLAN.md §9.1, T13) : Coordonnées → Relais →
 * Récapitulatif → Paiement. La commande est créée en BDD **au clic sur
 * « Payer »** (jamais avant), via `CreateOrderAction` (CLAUDE.md §4 :
 * recalcul serveur, jamais de confiance dans le navigateur).
 *
 * ⚠️ Étape "Relais" provisoire (PLAN.md §8.1, T11 non livrée faute des
 * identifiants Chronopost) : saisie manuelle d'un nom de relais au lieu
 * de la recherche réelle. N'invente aucun comportement Chronopost — à
 * remplacer dès que T11 est faite (voir docs/DECISIONS.md).
 */
class CheckoutWizard extends Component
{
    public int $step = 1;

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    // Règle en tableau (pas en chaîne "required|regex:...") : le "|" de
    // l'alternative (0|\+33) casserait sinon le séparateur de règles Laravel.
    #[Validate(['required', 'regex:/^(0|\+33)[67][0-9]{8}$/'])]
    public string $phone = '';

    #[Validate('required|string|max:255')]
    public string $billing_line1 = '';

    #[Validate('required|regex:/^[0-9]{5}$/')]
    public string $billing_postal_code = '';

    #[Validate('required|string|max:255')]
    public string $billing_city = '';

    #[Validate('required|string|max:5|regex:/^[0-9]{5}$/')]
    public string $relay_postal_code = '';

    #[Validate('required|string|max:255')]
    public string $relay_name = '';

    public bool $cgvAccepted = false;

    #[Validate('required')]
    public string $paymentMethod = 'stripe';

    public ?string $error = null;

    /** Garde-fou double-clic (en plus de `wire:loading.attr="disabled"` côté vue). */
    public bool $submitting = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            $this->email = $user->email;
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;
            $this->phone = (string) $user->phone;

            $address = $user->addresses()->first();
            if ($address) {
                $this->billing_line1 = $address->line1;
                $this->billing_postal_code = $address->postal_code;
                $this->billing_city = $address->city;
            }
        }
    }

    public function goToStep(int $step): void
    {
        if ($step === 2) {
            $this->validate([
                'email' => 'required|email|max:255',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone' => ['required', 'regex:/^(0|\+33)[67][0-9]{8}$/'],
                'billing_line1' => 'required|string|max:255',
                'billing_postal_code' => 'required|regex:/^[0-9]{5}$/',
                'billing_city' => 'required|string|max:255',
            ]);
        }

        if ($step === 3) {
            $this->validate([
                'relay_postal_code' => 'required|regex:/^[0-9]{5}$/',
                'relay_name' => 'required|string|max:255',
            ]);

            if (str_starts_with($this->relay_postal_code, '20')) {
                $this->addError('relay_postal_code', 'La livraison en Corse n\'est pas disponible (France métropolitaine uniquement).');

                return;
            }
        }

        $this->step = $step;
    }

    public function pay(): void
    {
        if ($this->submitting) {
            return;
        }
        try {
            $this->validate([
                'cgvAccepted' => 'accepted',
                'paymentMethod' => 'required|in:stripe,bank_transfer',
            ], [
                'cgvAccepted.accepted' => 'Vous devez accepter les CGV pour continuer.',
            ]);
        } catch (ValidationException $e) {
            $this->submitting = false;

            throw $e;
        }

        $this->submitting = true;

        $cartService = app(CartService::class);
        $cart = $cartService->currentCart();
        $totals = $cartService->totals($cart);

        if ($totals['shipping_error'] !== null) {
            $this->error = 'Configuration incomplète, impossible de finaliser la commande pour le moment. Contactez-nous.';
            $this->submitting = false;

            Log::critical('Tunnel de commande bloqué : configuration de livraison incomplète.', [
                'email' => $this->email,
                'shipping_error' => $totals['shipping_error'],
            ]);

            return;
        }

        $order = app(CreateOrderAction::class)->execute(
            cart: $cart,
            customer: [
                'email' => $this->email,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone,
            ],
            billing: [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'line1' => $this->billing_line1,
                'postal_code' => $this->billing_postal_code,
                'city' => $this->billing_city,
            ],
            relay: [
                'relay_id' => 'MANUEL-'.now()->timestamp,
                'relay_name' => $this->relay_name,
                'relay_snapshot' => [
                    'name' => $this->relay_name,
                    'postal_code' => $this->relay_postal_code,
                    'note' => 'Saisie manuelle provisoire (T11 non livrée)',
                ],
            ],
            paymentMethod: PaymentMethod::from($this->paymentMethod),
            userId: Auth::id(),
        );

        if ($order->payment_method === PaymentMethod::Stripe) {
            $this->redirect(route('checkout.stripe.start', $order), navigate: false);

            return;
        }

        $this->redirect(route('checkout.confirmation', $order->token), navigate: false);
    }

    public function render()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->currentCart();
        ['items' => $items] = $cartService->validItems($cart);

        if ($items->isEmpty() && $this->step < 4) {
            return view('livewire.checkout-wizard', ['items' => $items, 'totals' => null, 'empty' => true]);
        }

        return view('livewire.checkout-wizard', [
            'items' => $items,
            'totals' => $cartService->totals($cart),
            'empty' => false,
        ]);
    }
}
