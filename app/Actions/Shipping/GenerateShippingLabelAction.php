<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Exceptions\Shipping\ShippingLabelFailed;
use App\Models\Order;
use App\Models\ShippingLabel;
use App\Services\Chronopost\ShippingLabelClient;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Action BO « Générer l'étiquette Chronopost » (T28, PLAN.md §8.2).
 *
 * Ne change PAS le statut de la commande : la cliente clique ensuite
 * « Expédier » quand le colis part, avec le numéro de suivi pré-rempli
 * (décision de Ian du 26/10/2026).
 *
 * Idempotente : sous verrou, une commande qui a déjà une étiquette active la
 * renvoie sans rappeler Chronopost (double clic, deux onglets).
 */
class GenerateShippingLabelAction
{
    /** Statuts où le colis est en cours de préparation. */
    private const ALLOWED_STATUSES = [OrderStatus::Paid, OrderStatus::Preparing];

    public function __construct(
        private readonly ShippingLabelClient $client,
        private readonly ShippingSettings $shippingSettings,
    ) {}

    /** Bouton visible dans le BO (les contrôles complets sont refaits dans execute). */
    public static function isAvailableFor(Order $order): bool
    {
        return $order->delivery_method === DeliveryMethod::ChronopostRelay
            && in_array($order->status, self::ALLOWED_STATUSES, true);
    }

    /**
     * @throws ShippingLabelFailed commande non éligible ou refus/panne Chronopost
     */
    public function execute(Order $order, ?int $adminId): ShippingLabel
    {
        return DB::transaction(function () use ($order, $adminId): ShippingLabel {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = $locked->activeShippingLabel()->first();
            if ($existing !== null) {
                return $existing;
            }

            $this->assertEligible($locked);
            $weight = (int) $locked->items()->sum(DB::raw('weight_g * quantity'));

            $created = $this->client->create($locked, $weight);

            $path = "shipping-labels/{$locked->id}/{$created->skybillNumber}.pdf";
            Storage::disk('local')->put($path, $created->pdf);

            return $locked->shippingLabels()->create([
                'skybill_number' => $created->skybillNumber,
                'reservation_number' => $created->reservationNumber,
                'pdf_path' => $path,
                'weight_g' => $weight,
                'created_by_admin_id' => $adminId,
            ]);
        });
    }

    private function assertEligible(Order $order): void
    {
        if (! $this->shippingSettings->chronopost_enabled) {
            throw new ShippingLabelFailed('La livraison Chronopost est désactivée (Paramètres > Expédition).');
        }

        if (! self::isAvailableFor($order)) {
            throw new ShippingLabelFailed('Étiquette possible uniquement pour une commande Chronopost payée ou en préparation.');
        }

        // Relais saisi à la main (commande manuelle ou ancienne saisie provisoire) : pas de code Chronopost.
        $snapshot = (array) $order->relay_snapshot;
        if (blank($order->relay_id) || ! isset($snapshot['chronopost_id']) || $snapshot['chronopost_id'] !== $order->relay_id) {
            throw new ShippingLabelFailed('Le relais de cette commande n\'a pas été choisi dans la liste Chronopost : créez le colis dans votre outil Chronopost habituel.');
        }
    }
}
