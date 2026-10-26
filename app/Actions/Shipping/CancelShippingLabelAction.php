<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Enums\OrderStatus;
use App\Exceptions\Shipping\ShippingLabelFailed;
use App\Models\Order;
use App\Models\ShippingLabel;
use App\Services\Chronopost\ShippingLabelClient;
use Illuminate\Support\Facades\DB;

/**
 * Action BO « Annuler l'étiquette » (T28) : annule la LT chez Chronopost
 * (`cancelListSkybill`) puis la marque annulée. Le PDF est conservé
 * (traçabilité) ; une nouvelle étiquette peut ensuite être générée.
 * Impossible une fois le colis expédié.
 */
class CancelShippingLabelAction
{
    public function __construct(private readonly ShippingLabelClient $client) {}

    public static function isAvailableFor(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing], true)
            && $order->activeShippingLabel !== null;
    }

    /**
     * @throws ShippingLabelFailed aucune étiquette active, colis expédié, ou refus Chronopost
     */
    public function execute(Order $order): ShippingLabel
    {
        return DB::transaction(function () use ($order): ShippingLabel {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $label = $locked->activeShippingLabel()->first();

            if ($label === null || ! in_array($locked->status, [OrderStatus::Paid, OrderStatus::Preparing], true)) {
                throw new ShippingLabelFailed('Aucune étiquette annulable pour cette commande (déjà annulée ou colis expédié).');
            }

            $this->client->cancel($label->skybill_number, $locked);

            $label->cancelled_at = now();
            $label->save();

            return $label;
        });
    }
}
