<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Enums\OrderStatus;
use App\Exceptions\Shipping\ShippingLabelFailed;
use App\Models\Order;
use App\Models\ShippingLabel;
use App\Services\Chronopost\ShippingLabelClient;

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
        return self::statusAllowsCancel($order) && $order->activeShippingLabel !== null;
    }

    /**
     * Colis pas encore parti : commande payée / en préparation, ou remboursée /
     * annulée AVANT expédition (audit du 26/10/2026 : l'étiquette d'une commande
     * remboursée restait active sans moyen de l'annuler).
     */
    private static function statusAllowsCancel(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing], true)
            || (in_array($order->status, [OrderStatus::Refunded, OrderStatus::Cancelled], true) && $order->shipped_at === null);
    }

    /**
     * @throws ShippingLabelFailed aucune étiquette active, colis expédié, ou refus Chronopost
     */
    public function execute(Order $order): ShippingLabel
    {
        // Même verrou que la génération ; pas de transaction pendant l'appel réseau (audit du 26/10/2026).
        $lock = GenerateShippingLabelAction::lockFor($order);
        if (! $lock->get()) {
            throw new ShippingLabelFailed('Une opération sur l\'étiquette de cette commande est déjà en cours : patientez quelques secondes.');
        }

        try {
            $fresh = Order::query()->findOrFail($order->id);
            $label = $fresh->activeShippingLabel()->first();

            if ($label === null || ! self::statusAllowsCancel($fresh)) {
                throw new ShippingLabelFailed('Aucune étiquette annulable pour cette commande (déjà annulée ou colis expédié).');
            }

            $this->client->cancel($label->skybill_number, $fresh);

            $label->cancelled_at = now();
            $label->save();

            return $label;
        } finally {
            $lock->release();
        }
    }
}
