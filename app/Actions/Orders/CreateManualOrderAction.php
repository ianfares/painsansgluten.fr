<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\ManualOrderNotAllowed;
use App\Mail\BankTransferInstructionsMail;
use App\Mail\ManualOrderPaymentRequestMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\DeliveryOptions;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Commande créée à la main par l'admin pour un client existant (T27-L10).
 * Calcul strictement identique au tunnel : les lignes passent par un panier
 * temporaire rattaché au client (remise du compte comprise) puis par
 * `CreateOrderAction` (prix, TVA, port, date). Aucun montant n'est saisi.
 * Le panier temporaire n'existe que dans la transaction : il n'est jamais
 * visible du client et son propre panier n'est pas touché.
 */
class CreateManualOrderAction
{
    public function __construct(
        private readonly CreateOrderAction $createOrderAction,
        private readonly DeliveryOptions $deliveryOptions,
    ) {}

    /**
     * @param  list<array{product_id: int|string, quantity: int|string}>  $lines
     * @param  array{line1: string, postal_code: string, city: string}  $billing
     * @param  array{relay_name?: ?string, relay_postal_code?: ?string, pickup_point_id?: int|string|null}  $delivery
     *
     * @throws ManualOrderNotAllowed
     */
    public function execute(int $adminId, User $user, array $lines, DeliveryMethod $deliveryMethod, array $delivery, array $billing, PaymentMethod $paymentMethod): Order
    {
        if ($user->isDeactivated()) {
            throw ManualOrderNotAllowed::because('Ce compte client est désactivé.');
        }

        if (! in_array($paymentMethod, [PaymentMethod::Stripe, PaymentMethod::BankTransfer], true)) {
            throw ManualOrderNotAllowed::because('Choisissez la carte bancaire ou le virement.');
        }

        $relay = $this->relayFor($user, $deliveryMethod, $delivery);
        $quantities = $this->quantities($lines);

        $order = DB::transaction(function () use ($adminId, $user, $quantities, $deliveryMethod, $relay, $billing, $paymentMethod) {
            $cart = Cart::query()->create(['user_id' => $user->id, 'session_id' => 'admin-order-'.Str::random(32)]);
            foreach ($quantities as $productId => $quantity) {
                $cart->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
            }

            try {
                $order = $this->createOrderAction->execute(
                    cart: $cart,
                    customer: ['email' => $user->email, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'phone' => (string) $user->phone],
                    billing: ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'company' => $user->company_name, ...$billing],
                    relay: $relay,
                    paymentMethod: $paymentMethod,
                    userId: $user->id,
                    deliveryMethod: $deliveryMethod,
                );
            } catch (RuntimeException $e) {
                throw ManualOrderNotAllowed::because('Commande impossible : '.$e->getMessage());
            }

            $cart->delete();
            $order->forceFill(['created_by_admin_id' => $adminId])->save();

            return $order;
        });

        Mail::to($order->email)->queue($paymentMethod === PaymentMethod::Stripe
            ? new ManualOrderPaymentRequestMail($order)
            : new BankTransferInstructionsMail($order));

        return $order;
    }

    /**
     * @param  array{relay_name?: ?string, relay_postal_code?: ?string, pickup_point_id?: int|string|null}  $delivery
     * @return array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>}
     */
    private function relayFor(User $user, DeliveryMethod $method, array $delivery): array
    {
        return match ($method) {
            DeliveryMethod::ChronopostRelay => $this->chronopostRelay($delivery),
            // L'admin peut choisir tout commerçant actif et localisé : la règle des 50 km ne vise que le tunnel client.
            DeliveryMethod::MerchantPickup => ($point = PickupPoint::query()->bookable()->find($delivery['pickup_point_id'] ?? null)) instanceof PickupPoint
                ? $this->deliveryOptions->merchantSnapshot($point, 0.0)
                : throw ManualOrderNotAllowed::because('Choisissez un point de retrait actif.'),
            DeliveryMethod::LabPickup => $this->deliveryOptions->labPickupAllowed($user)
                ? $this->deliveryOptions->labSnapshot()
                : throw ManualOrderNotAllowed::because('Le retrait au laboratoire n\'est pas autorisé pour ce client.'),
        };
    }

    /**
     * @param  array{relay_name?: ?string, relay_postal_code?: ?string}  $delivery
     * @return array{relay_id: string, relay_name: string, relay_snapshot: array<string, mixed>}
     */
    private function chronopostRelay(array $delivery): array
    {
        $name = trim((string) ($delivery['relay_name'] ?? ''));
        $postalCode = trim((string) ($delivery['relay_postal_code'] ?? ''));

        if ($name === '' || ! preg_match('/^\d{5}$/', $postalCode) || str_starts_with($postalCode, '20')) {
            throw ManualOrderNotAllowed::because('Indiquez le relais Chronopost (nom et code postal, France métropolitaine hors Corse).');
        }

        return [
            'relay_id' => 'MANUEL-'.now()->timestamp,
            'relay_name' => $name,
            'relay_snapshot' => ['name' => $name, 'postal_code' => $postalCode, 'note' => 'Saisie par l\'admin (commande manuelle)'],
        ];
    }

    /**
     * Quantités par produit : lignes en double regroupées, plafonnées comme dans le panier.
     *
     * @param  list<array{product_id: int|string, quantity: int|string}>  $lines
     * @return array<int, int>
     */
    private function quantities(array $lines): array
    {
        $max = app(ShippingSettings::class)->max_quantity_per_line;
        $quantities = [];
        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + max(0, (int) $line['quantity']);
        }

        $quantities = array_filter(array_map(fn (int $q) => min($max, $q), $quantities));
        $existing = Product::query()->whereKey(array_keys($quantities))->pluck('id')->all();

        if ($quantities === [] || count($existing) !== count($quantities)) {
            throw ManualOrderNotAllowed::because('Ajoutez au moins un produit existant.');
        }

        return $quantities;
    }
}
