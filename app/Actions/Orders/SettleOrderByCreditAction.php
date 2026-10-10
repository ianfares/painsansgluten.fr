<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\ManualOrderNotAllowed;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderStateMachine;
use App\Services\Shipping\ShippingDateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * « Valider sans paiement (avoir) » (T27-L10) : l'admin règle une commande
 * en attente par un avoir ou un geste commercial. Aucun encaissement : la
 * commande passe payée via la machine à états (facture émise par le listener
 * habituel), le moyen de paiement devient « Réglé par avoir » et la
 * référence/motif figure sur la facture. Mention à faire valider par le
 * comptable de la cliente.
 */
class SettleOrderByCreditAction
{
    public function __construct(
        private readonly OrderStateMachine $orderStateMachine,
        private readonly ShippingDateCalculator $shippingDateCalculator,
    ) {}

    /** @throws ManualOrderNotAllowed */
    public function execute(Order $order, string $reference, int $adminId): Order
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw ManualOrderNotAllowed::because('Indiquez la référence ou le motif de l\'avoir.');
        }

        return DB::transaction(function () use ($order, $reference, $adminId) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [OrderStatus::PendingPayment, OrderStatus::PaymentFailed], true)) {
                throw ManualOrderNotAllowed::because('Cette commande n\'est plus en attente de paiement.');
            }

            $locked->forceFill([
                'payment_method' => PaymentMethod::CreditSettlement,
                'settlement_reference' => mb_substr($reference, 0, 150),
                'planned_ship_date' => Carbon::instance($this->shippingDateCalculator->forInstant(CarbonImmutable::now())),
            ])->save();

            Payment::query()->create([
                'order_id' => $locked->id,
                'method' => PaymentMethod::CreditSettlement,
                'provider_ref' => mb_substr($reference, 0, 150),
                'amount' => $locked->total_ttc,
                'status' => 'paid',
            ]);

            return $this->orderStateMachine->transition(
                $locked,
                OrderStatus::Paid,
                actorType: 'admin',
                actorId: $adminId,
                comment: 'Réglé par avoir : '.mb_substr($reference, 0, 150),
            );
        });
    }
}
