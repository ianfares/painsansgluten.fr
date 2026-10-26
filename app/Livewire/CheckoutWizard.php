<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\OrderBelowMinimum;
use App\Exceptions\Shipping\RelayServiceUnavailable;
use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Livewire\Concerns\PushesAnalyticsEvents;
use App\Mail\Admin\NewBankTransferOrderMail;
use App\Mail\BankTransferInstructionsMail;
use App\Models\CartItem;
use App\Models\PickupPoint;
use App\Models\User;
use App\Services\Analytics\Ecommerce;
use App\Services\Cart\CartService;
use App\Services\Chronopost\RelayPoint;
use App\Services\Chronopost\RelayPointClient;
use App\Services\Mail\AdminMailer;
use App\Services\Shipping\DeliveryOptions;
use App\Services\Shipping\ShippingDateCalculator;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RuntimeException;

/**
 * Tunnel de commande (PLAN.md §9.1, T13) : Coordonnées → Relais →
 * Récapitulatif → Paiement. La commande est créée en BDD **au clic sur
 * « Payer »** (jamais avant), via `CreateOrderAction` (CLAUDE.md §4 :
 * recalcul serveur, jamais de confiance dans le navigateur).
 *
 * Étape "Relais" (T11, PLAN.md §8.1) : recherche des relais Chronopost par
 * code postal + ville, puis choix dans la liste. Seul l'identifiant choisi
 * vient du navigateur : le relais est relu côté serveur au moment de valider.
 */
class CheckoutWizard extends Component
{
    use PushesAnalyticsEvents;

    /** Modifiée uniquement côté serveur (goToStep), jamais par le navigateur. */
    #[Locked]
    public int $step = 1;

    private const RELAY_SEARCHES_PER_MINUTE = 10;

    /** Commandes créées max par fenêtre de 10 min (anti-abus d'emails). */
    private const ORDERS_PER_EMAIL = 3;

    private const ORDERS_PER_IP = 10;

    private const ORDER_LIMIT_WINDOW_SECONDS = 600;

    private const RELAY_SERVICE_DOWN_MESSAGE = 'Service de points relais momentanément indisponible, réessayez dans quelques instants.';

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

    /** Adresse de livraison (11/10/2026) : identique à la facturation par défaut. */
    public bool $shippingSameAsBilling = true;

    public string $shipping_first_name = '';

    public string $shipping_last_name = '';

    public string $shipping_line1 = '';

    public string $shipping_postal_code = '';

    public string $shipping_city = '';

    public string $relay_postal_code = '';

    public string $relay_city = '';

    /** Identifiant Chronopost du relais choisi ; revérifié contre la recherche en cache. */
    public string $relay_id = '';

    /**
     * Résultats affichés (RelayPoint::toArray). Verrouillés : le navigateur ne
     * peut pas injecter un faux relais, et la sélection est relue côté serveur.
     *
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $relayResults = [];

    /** Code postal + ville de la dernière recherche réussie (relecture du cache au paiement). */
    #[Locked]
    public string $relaySearchPostalCode = '';

    #[Locked]
    public string $relaySearchCity = '';

    /**
     * Choix de l'étape 2 (T27-L7b) : « chronopost_relay », « lab_pickup » ou
     * « merchant_pickup:{id} ». Un seul champ pour un seul groupe de boutons
     * radio ; revalidé côté serveur à chaque étape et au paiement.
     */
    public string $deliveryChoice = 'chronopost_relay';

    public bool $cgvAccepted = false;

    #[Validate('required')]
    public string $paymentMethod = 'stripe';

    public ?string $error = null;

    /** Garde-fou double-clic (en plus de `wire:loading.attr="disabled"` côté vue). */
    #[Locked]
    public bool $submitting = false;

    public function mount(): void
    {
        if ($this->cartIsPickupOnly() || ! $this->chronopostEnabled()) {
            $this->deliveryChoice = ''; // Chronopost impossible : le client choisit un retrait
        }

        $this->pushAnalytics('begin_checkout', $this->analyticsCart());

        if (Auth::check()) {
            $user = Auth::user();
            $this->email = $user->email;
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;
            $this->phone = (string) $user->phone;

            $address = $user->billingAddress();
            if ($address) {
                $this->billing_line1 = $address->line1;
                $this->billing_postal_code = $address->postal_code;
                $this->billing_city = $address->city;
            }

            $shipping = $user->shippingAddress();
            if ($shipping) {
                $this->shippingSameAsBilling = false;
                $this->shipping_first_name = $shipping->first_name;
                $this->shipping_last_name = $shipping->last_name;
                $this->shipping_line1 = $shipping->line1;
                $this->shipping_postal_code = $shipping->postal_code;
                $this->shipping_city = $shipping->city;
            }
        }
    }

    public function goToStep(int $step): void
    {
        if ($step === 2) {
            $this->validate($this->customerRules());
            $this->prefillRelaySearch();
        }

        if ($step === 3 && $this->resolveDelivery() === null) {
            return;
        }

        if ($step === 3 && $this->step === 2) {
            $this->pushAnalytics('add_shipping_info', $this->analyticsCart() + ['shipping_tier' => $this->selectedDeliveryMethod()->label()]);
        }

        $this->step = $step;
    }

    /** @return array<string, mixed> */
    private function customerRules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^(0|\+33)[67][0-9]{8}$/'],
            'billing_line1' => 'required|string|max:255',
            'billing_postal_code' => 'required|regex:/^[0-9]{5}$/',
            'billing_city' => 'required|string|max:255',
            ...($this->shippingSameAsBilling ? [] : [
                'shipping_first_name' => 'required|string|max:255',
                'shipping_last_name' => 'required|string|max:255',
                'shipping_line1' => 'required|string|max:255',
                'shipping_postal_code' => 'required|regex:/^[0-9]{5}$/',
                'shipping_city' => 'required|string|max:255',
            ]),
        ];
    }

    /**
     * Adresse qui sert à chercher les points de retrait proches : la livraison
     * si elle est distincte, sinon la facturation.
     *
     * @return array{line1: string, postal_code: string, city: string}
     */
    private function deliveryAddress(): array
    {
        return $this->shippingSameAsBilling
            ? ['line1' => $this->billing_line1, 'postal_code' => $this->billing_postal_code, 'city' => $this->billing_city]
            : ['line1' => $this->shipping_line1, 'postal_code' => $this->shipping_postal_code, 'city' => $this->shipping_city];
    }

    /**
     * Recherche les relais Chronopost (bouton « Rechercher »). Les erreurs du
     * service sont affichées sans bloquer les autres modes de livraison.
     */
    public function searchRelays(): void
    {
        $this->relayResults = [];
        $this->relay_id = '';
        $this->relaySearchPostalCode = '';
        $this->relaySearchCity = '';

        $this->validate($this->relaySearchRules(), $this->relaySearchMessages());
        if ($this->relayOutsideMainland()) {
            return;
        }

        // Anti-abus : chaque recherche appelle Chronopost (hors cache).
        $limiterKey = 'relay-search:'.request()->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, self::RELAY_SEARCHES_PER_MINUTE)) {
            $this->addError('relay_postal_code', 'Trop de recherches en peu de temps. Merci de réessayer dans une minute.');

            return;
        }
        RateLimiter::hit($limiterKey, 60);

        try {
            $points = app(RelayPointClient::class)->search($this->relay_postal_code, $this->relay_city);
        } catch (RelayServiceUnavailable) {
            $this->addError('relay_postal_code', self::RELAY_SERVICE_DOWN_MESSAGE);

            return;
        }

        if ($points === []) {
            $this->addError('relay_postal_code', 'Aucun point relais trouvé pour ce code postal et cette ville. Vérifiez l\'orthographe de la ville ou essayez une commune voisine.');

            return;
        }

        $this->relayResults = array_map(fn (RelayPoint $point): array => $point->toArray(), $points);
        $this->relaySearchPostalCode = $this->relay_postal_code;
        $this->relaySearchCity = $this->relay_city;
    }

    /** Recherche pré-remplie avec l'adresse de livraison, sans écraser une saisie. */
    private function prefillRelaySearch(): void
    {
        if ($this->relay_postal_code === '' && $this->relay_city === '') {
            $address = $this->deliveryAddress();
            $this->relay_postal_code = $address['postal_code'];
            $this->relay_city = $address['city'];
        }
    }

    /** @return array<string, mixed> */
    private function relaySearchRules(): array
    {
        return [
            'relay_postal_code' => 'required|regex:/^[0-9]{5}$/',
            'relay_city' => 'required|string|max:50',
        ];
    }

    /** @return array<string, string> */
    private function relaySearchMessages(): array
    {
        return [
            'relay_postal_code.required' => 'Indiquez le code postal où chercher un relais.',
            'relay_postal_code.regex' => 'Le code postal doit comporter 5 chiffres.',
            'relay_city.required' => 'Indiquez la ville où chercher un relais.',
        ];
    }

    /** Corse (20xxx) et outre-mer (97xxx, 98xxx) : pas de livraison (PLAN.md §8.1). */
    private function relayOutsideMainland(): bool
    {
        $message = match (true) {
            str_starts_with($this->relay_postal_code, '20') => 'La livraison en Corse n\'est pas disponible (France métropolitaine uniquement).',
            str_starts_with($this->relay_postal_code, '97'), str_starts_with($this->relay_postal_code, '98') => 'La livraison est disponible uniquement en France métropolitaine (hors Corse).',
            default => null,
        };

        if ($message !== null) {
            $this->addError('relay_postal_code', $message);
        }

        return $message !== null;
    }

    private function selectedDeliveryMethod(): DeliveryMethod
    {
        return DeliveryMethod::tryFrom(explode(':', $this->deliveryChoice)[0]) ?? DeliveryMethod::ChronopostRelay;
    }

    private function selectedPickupPointId(): ?int
    {
        $id = explode(':', $this->deliveryChoice)[1] ?? '';

        return ctype_digit($id) ? (int) $id : null;
    }

    /**
     * Valide le mode de livraison choisi et retourne le snapshot à enregistrer
     * sur la commande ; null (avec message d'erreur) si le choix n'est pas
     * permis. Tout est recalculé ici : distance du commerçant, droit au
     * retrait labo — jamais repris du navigateur (T27-L7b).
     *
     * @return array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>}|null
     */
    private function resolveDelivery(): ?array
    {
        // Erreur d'un choix précédent (ex. minimum non atteint) effacée à chaque nouvelle tentative.
        $this->resetErrorBag('deliveryChoice');

        if (DeliveryMethod::tryFrom(explode(':', $this->deliveryChoice)[0]) === null) {
            return $this->deliveryError('deliveryChoice', 'Choisissez un mode de livraison.');
        }

        $options = app(DeliveryOptions::class);

        if ($this->selectedDeliveryMethod() === DeliveryMethod::ChronopostRelay && $this->cartIsPickupOnly()) {
            return $this->deliveryError('deliveryChoice', 'Votre panier contient un produit disponible uniquement en retrait : choisissez un point de retrait.');
        }

        if ($this->selectedDeliveryMethod() === DeliveryMethod::ChronopostRelay && ! $this->chronopostEnabled()) {
            return $this->deliveryError('deliveryChoice', 'La livraison Chronopost est momentanément indisponible : choisissez un point de retrait.');
        }

        // Minimum de commande du mode choisi (19/10/2026), revérifié par CreateOrderAction.
        $cartService = app(CartService::class);
        $totals = $cartService->totals($cartService->currentCart(), $this->selectedDeliveryMethod());
        if ($totals['minimum_missing_ttc'] > 0) {
            return $this->deliveryError('deliveryChoice', OrderBelowMinimum::forAmounts($totals['minimum_ttc'], $totals['minimum_missing_ttc'])->getMessage());
        }

        return match ($this->selectedDeliveryMethod()) {
            DeliveryMethod::ChronopostRelay => $this->resolveChronopostRelay(),
            DeliveryMethod::MerchantPickup => $this->resolveMerchantPickup($options),
            DeliveryMethod::LabPickup => $options->labPickupAllowed(Auth::user())
                ? $options->labSnapshot()
                : $this->deliveryError('deliveryChoice', 'Le retrait au laboratoire n\'est pas disponible pour votre compte.'),
        };
    }

    /** @return array<string, mixed> panier au format GA4 (T22) */
    private function analyticsCart(): array
    {
        $cartService = app(CartService::class);

        return app(Ecommerce::class)->cart($cartService->validItems($cartService->currentCart())['items'], Auth::user());
    }

    /** Un produit non expédiable dans le panier : Chronopost n'est pas proposé. */
    private function cartIsPickupOnly(): bool
    {
        $cartService = app(CartService::class);

        return $cartService->validItems($cartService->currentCart())['items']
            ->contains(fn (CartItem $item) => $item->product->isPickupOnly());
    }

    /**
     * Jours de retrait du commerçant choisi (24/10/2026), pour la date
     * affichée au récapitulatif ; recalculés par CreateOrderAction au paiement.
     *
     * @return list<string>|null
     */
    private function selectedPickupWeekdays(DeliveryMethod $deliveryMethod): ?array
    {
        $id = $this->selectedPickupPointId();
        if ($deliveryMethod !== DeliveryMethod::MerchantPickup || $id === null) {
            return null;
        }

        $weekdays = PickupPoint::query()->find($id)?->pickup_weekdays;

        return $weekdays ?: null;
    }

    /** Premier jour de retrait possible chez ce commerçant ; null si les réglages d'expédition sont incomplets. */
    private function pickupDate(PickupPoint $point): ?CarbonImmutable
    {
        try {
            return app(ShippingDateCalculator::class)->forInstant(CarbonImmutable::now(), $point->pickup_weekdays ?: null);
        } catch (ShippingNotConfigured) {
            return null;
        }
    }

    /** Interrupteur du BO (24/10/2026) : coupé, seul le retrait chez un commerçant est proposé. */
    private function chronopostEnabled(): bool
    {
        return app(ShippingSettings::class)->chronopost_enabled;
    }

    /**
     * Relais choisi, relu dans la recherche Chronopost en cache (jamais repris
     * du navigateur) puis copié dans la commande (snapshot, PLAN.md §8.1).
     *
     * @return array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>}|null
     */
    private function resolveChronopostRelay(): ?array
    {
        if ($this->relay_id === '' || $this->relaySearchPostalCode === '') {
            return $this->deliveryError('relay_id', 'Recherchez puis choisissez un point relais.');
        }

        try {
            $point = app(RelayPointClient::class)->find($this->relaySearchPostalCode, $this->relaySearchCity, $this->relay_id);
        } catch (RelayServiceUnavailable) {
            return $this->deliveryError('relay_id', self::RELAY_SERVICE_DOWN_MESSAGE);
        }

        if ($point === null) {
            return $this->deliveryError('relay_id', 'Ce point relais n\'est plus disponible : relancez la recherche et choisissez-en un autre.');
        }

        return [
            'relay_id' => $point->id,
            'relay_name' => $point->name,
            'relay_snapshot' => [
                'name' => $point->name,
                'address_line1' => $point->addressLine1,
                'postal_code' => $point->postalCode,
                'city' => $point->city,
                'chronopost_id' => $point->id,
                'opening_hours' => $point->openingHoursLabel(),
                'closures' => $point->closures,
            ],
        ];
    }

    /** @return array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>}|null */
    private function resolveMerchantPickup(DeliveryOptions $options): ?array
    {
        $pointId = $this->selectedPickupPointId();
        $option = $pointId === null
            ? null
            : $options->eligibleMerchantPoint($pointId, ...array_values($this->deliveryAddress()));

        return $option === null
            ? $this->deliveryError('deliveryChoice', 'Choisissez un point de retrait proposé pour votre adresse.')
            : $options->merchantSnapshot($option['point'], $option['distance_km']);
    }

    private function deliveryError(string $field, string $message): null
    {
        $this->addError($field, $message);

        return null;
    }

    public function pay(): void
    {
        if ($this->submitting) {
            return;
        }

        // Toutes les étapes sont revalidées ici : `pay` peut être appelée
        // directement par une requête Livewire, sans passer par les étapes.
        try {
            $this->validate($this->customerRules());
        } catch (ValidationException $e) {
            $this->step = 1;

            throw $e;
        }

        try {
            $relay = $this->resolveDelivery();
        } catch (ValidationException $e) {
            $this->step = 2;

            throw $e;
        }

        if ($relay === null) {
            $this->step = 2;

            return;
        }

        $this->validate([
            'cgvAccepted' => 'accepted',
            // Virement réservé aux pros autorisés (15/10/2026) ; revérifié à la création de la commande.
            'paymentMethod' => $this->bankTransferAllowed() ? 'required|in:stripe,bank_transfer' : 'required|in:stripe',
        ], [
            'cgvAccepted.accepted' => 'Vous devez accepter les CGV pour continuer.',
            'paymentMethod.in' => 'Le paiement par virement est réservé aux comptes professionnels autorisés : choisissez la carte bancaire.',
        ]);

        // Anti-abus : chaque commande envoie des emails à l'adresse saisie. Seules les
        // commandes réellement créées comptent (audit du 26/10/2026 : un échec ne bloque plus
        // le client), par adresse email ET par IP (l'email seul se contourne en le changeant).
        $limiterKeys = $this->orderLimiterKeys();
        foreach ($limiterKeys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->error = 'Trop de commandes ont été passées en peu de temps. Merci de réessayer dans quelques minutes.';

                return;
            }
        }

        $this->pushAnalytics('add_payment_info', $this->analyticsCart() + ['payment_type' => $this->paymentMethod === 'stripe' ? 'Carte bancaire' : 'Virement']);

        $this->submitting = true;

        $cartService = app(CartService::class);
        $cart = $cartService->currentCart();
        $totals = $cartService->totals($cart, $this->selectedDeliveryMethod());

        if ($totals['shipping_error'] !== null) {
            $this->error = 'Configuration incomplète, impossible de finaliser la commande pour le moment. Contactez-nous.';
            $this->submitting = false;

            Log::critical('Tunnel de commande bloqué : configuration de livraison incomplète.', [
                'shipping_error' => $totals['shipping_error'],
            ]);

            return;
        }

        try {
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
                relay: $relay,
                paymentMethod: PaymentMethod::from($this->paymentMethod),
                userId: Auth::id(),
                deliveryMethod: $this->selectedDeliveryMethod(),
                shipping: $this->shippingSameAsBilling ? null : [
                    'first_name' => $this->shipping_first_name,
                    'last_name' => $this->shipping_last_name,
                    'line1' => $this->shipping_line1,
                    'postal_code' => $this->shipping_postal_code,
                    'city' => $this->shipping_city,
                ],
            );
        } catch (OrderBelowMinimum $e) {
            // Panier modifié dans un autre onglet après l'étape 2.
            $this->error = $e->getMessage();
            $this->submitting = false;

            return;
        } catch (RuntimeException) {
            // Panier vidé entre-temps (commande déjà validée dans un autre onglet, double clic).
            $this->error = 'Votre panier est vide ou cette commande a déjà été validée.';
            $this->submitting = false;

            return;
        }

        foreach (array_keys($limiterKeys) as $key) {
            RateLimiter::hit($key, self::ORDER_LIMIT_WINDOW_SECONDS);
        }

        if ($order->payment_method === PaymentMethod::Stripe) {
            $this->redirect(route('checkout.stripe.start', $order), navigate: false);

            return;
        }

        Mail::to($order->email)->queue(new BankTransferInstructionsMail($order));
        AdminMailer::queue(new NewBankTransferOrderMail($order));

        $this->redirect(route('checkout.confirmation', $order->token), navigate: false);
    }

    /**
     * Clés du limiteur de commandes => nombre max de commandes créées par fenêtre.
     *
     * @return array<string, int>
     */
    private function orderLimiterKeys(): array
    {
        return [
            'checkout:email:'.sha1(mb_strtolower($this->email)) => self::ORDERS_PER_EMAIL,
            'checkout:ip:'.request()->ip() => self::ORDERS_PER_IP,
        ];
    }

    /** Particuliers et invités : carte seule ; pros validés : virement sauf s'il a été désactivé dans le BO. */
    private function bankTransferAllowed(): bool
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->canPayByBankTransfer();
    }

    public function render()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->currentCart();
        ['items' => $items] = $cartService->validItems($cart);

        if ($items->isEmpty() && $this->step < 4) {
            return view('livewire.checkout-wizard', ['items' => $items, 'totals' => null, 'empty' => true]);
        }

        $options = app(DeliveryOptions::class);
        $pickupOnly = $items->contains(fn (CartItem $item) => $item->product->isPickupOnly());
        $chronopostEnabled = $this->chronopostEnabled();
        $deliveryMethod = $this->selectedDeliveryMethod();
        if (($pickupOnly || ! $chronopostEnabled) && $deliveryMethod === DeliveryMethod::ChronopostRelay) {
            $deliveryMethod = DeliveryMethod::MerchantPickup; // aucun port facturé tant que le retrait n'est pas choisi
        }

        return view('livewire.checkout-wizard', [
            'items' => $items,
            'pickupOnly' => $pickupOnly,
            'chronopostEnabled' => $chronopostEnabled,
            'pickupOnlyNames' => $items->filter(fn (CartItem $item) => $item->product->isPickupOnly())->map(fn (CartItem $item) => $item->product->name)->values(),
            'totals' => $cartService->totals($cart, $deliveryMethod, $this->selectedPickupWeekdays($deliveryMethod)),
            'empty' => false,
            'deliveryMethodValue' => $deliveryMethod,
            'merchantOptions' => $this->step === 2
                ? $options->merchantPointsNear(...array_values($this->deliveryAddress()))->map(fn (array $option) => $option + [
                    'pickup_date' => $this->pickupDate($option['point']),
                    'pickup_hours' => PickupPoint::formatPickupHours($option['point']->pickup_from, $option['point']->pickup_until),
                ])
                : collect(),
            'labPickupAllowed' => $options->labPickupAllowed(Auth::user()),
            'bankTransferAllowed' => $this->bankTransferAllowed(),
            'labSnapshot' => $options->labSnapshot()['relay_snapshot'],
            'relayDayLabels' => RelayPoint::DAY_LABELS,
        ]);
    }
}
