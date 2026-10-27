<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\DeliveryMethod;
use App\Models\Cart;
use App\Models\CheckoutBlock;
use Throwable;

/**
 * Note un refus de commande pour minimum non atteint. Un même panier refusé
 * plusieurs fois au même montant et pour le même mode dans la demi-heure ne
 * compte qu'une fois (clics répétés sur « Continuer »). Une erreur
 * d'enregistrement est signalée sans jamais gêner le client.
 */
class RecordMinimumOrderBlockAction
{
    private const DEDUPLICATE_MINUTES = 30;

    public function execute(Cart $cart, DeliveryMethod $deliveryMethod, int $subtotalTtc, int $minimumTtc): void
    {
        if (! $cart->exists) {
            return;
        }

        try {
            $this->record($cart, $deliveryMethod, $subtotalTtc, $minimumTtc);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function record(Cart $cart, DeliveryMethod $deliveryMethod, int $subtotalTtc, int $minimumTtc): void
    {
        $alreadyRecorded = CheckoutBlock::query()
            ->where('cart_id', $cart->id)
            ->where('reason', CheckoutBlock::REASON_MINIMUM_ORDER)
            ->where('delivery_method', $deliveryMethod->value)
            ->where('subtotal_ttc', $subtotalTtc)
            ->where('created_at', '>=', now()->subMinutes(self::DEDUPLICATE_MINUTES))
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        CheckoutBlock::query()->create([
            'cart_id' => $cart->id,
            'reason' => CheckoutBlock::REASON_MINIMUM_ORDER,
            'delivery_method' => $deliveryMethod->value,
            'subtotal_ttc' => $subtotalTtc,
            'minimum_ttc' => $minimumTtc,
        ]);
    }
}
