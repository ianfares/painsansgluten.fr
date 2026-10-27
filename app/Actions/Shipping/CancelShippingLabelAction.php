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
    /** Délai Chronopost avant qu'une LT puisse être annulée (doc WS §2.6.4). */
    private const CANCEL_DELAY_MINUTES = 10;

    /** Au-delà, un refus n'est plus attribué au délai et le message Chronopost est affiché tel quel. */
    private const EARLY_CANCEL_WINDOW_MINUTES = 15;

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

            try {
                $this->client->cancel($label->skybill_number, $fresh);
            } catch (ShippingLabelFailed $e) {
                throw self::explainEarlyCancel($label, $e);
            }

            $label->cancelled_at = now();
            $label->save();

            return $label;
        } finally {
            $lock->release();
        }
    }

    /**
     * Chronopost n'accepte l'annulation qu'environ 10 minutes après la création
     * de la LT (doc WS §2.6.4) et répond sinon « System error (code 1) » :
     * message explicite tant que l'étiquette est récente (marge jusqu'à 15 min).
     */
    private static function explainEarlyCancel(ShippingLabel $label, ShippingLabelFailed $e): ShippingLabelFailed
    {
        if ($label->created_at === null || $label->created_at->lt(now()->subMinutes(self::EARLY_CANCEL_WINDOW_MINUTES))) {
            return $e;
        }

        $retryAt = $label->created_at->copy()->addMinutes(self::CANCEL_DELAY_MINUTES + 1)->timezone('Europe/Paris')->format('H:i');

        return new ShippingLabelFailed(
            'Chronopost n\'accepte l\'annulation d\'une étiquette qu\'environ 10 minutes après sa création : réessayez à partir de '.$retryAt.'.',
            previous: $e,
        );
    }
}
