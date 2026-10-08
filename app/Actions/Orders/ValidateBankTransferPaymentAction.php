<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\BankTransferValidationNotAllowed;
use App\Models\Order;
use App\Services\Orders\OrderStateMachine;
use App\Services\Shipping\ShippingDateCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Action BO « Valider le virement reçu » (PLAN.md §11, T15) : l'admin a
 * vérifié manuellement sur le relevé bancaire que le montant et la
 * référence correspondent (l'écran d'appel le lui rappelle), puis déclenche
 * cette action. La date d'expédition est recalculée à partir d'aujourd'hui
 * (le virement a pu arriver plusieurs jours après la commande).
 */
class ValidateBankTransferPaymentAction
{
    public function __construct(
        private readonly OrderStateMachine $orderStateMachine,
        private readonly ShippingDateCalculator $shippingDateCalculator,
    ) {}

    /**
     * @throws BankTransferValidationNotAllowed
     */
    public function execute(Order $order, int $adminId): Order
    {
        $order = DB::transaction(function () use ($order, $adminId) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_method !== PaymentMethod::BankTransfer) {
                throw BankTransferValidationNotAllowed::notBankTransfer();
            }

            if ($locked->status !== OrderStatus::PendingPayment) {
                throw BankTransferValidationNotAllowed::notPending();
            }

            $locked->planned_ship_date = Carbon::instance($this->shippingDateCalculator->forInstant(CarbonImmutable::now()));
            $locked->save();

            return $this->orderStateMachine->transition(
                $locked,
                OrderStatus::Paid,
                actorType: 'admin',
                actorId: $adminId,
                comment: 'Virement reçu et vérifié manuellement.',
            );
        });

        return $order;
    }
}
