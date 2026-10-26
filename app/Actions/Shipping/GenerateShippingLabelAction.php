<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Exceptions\Shipping\ShippingLabelFailed;
use App\Models\Order;
use App\Models\ShippingLabel;
use App\Services\Chronopost\CreatedLabel;
use App\Services\Chronopost\ShippingLabelClient;
use App\Settings\ShippingSettings;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Action BO « Générer l'étiquette Chronopost » (T28, PLAN.md §8.2).
 *
 * Ne change PAS le statut de la commande : la cliente clique ensuite
 * « Expédier » quand le colis part, avec le numéro de suivi pré-rempli
 * (décision de Ian du 26/10/2026).
 *
 * Idempotente : sous un verrou applicatif par commande, une commande qui a
 * déjà une étiquette active la renvoie sans rappeler Chronopost (double
 * clic, deux onglets).
 *
 * L'appel Chronopost est fait HORS transaction BDD (audit du 26/10/2026) : un
 * verrou de ligne tenu pendant un appel réseau bloquerait la commande, et une
 * erreur d'écriture après la création chez Chronopost laisserait une LT
 * orpheline. Si l'enregistrement échoue, le PDF est supprimé et la LT est
 * annulée chez Chronopost (au mieux, journalisé sinon).
 */
class GenerateShippingLabelAction
{
    /** Statuts où le colis est en cours de préparation. */
    private const ALLOWED_STATUSES = [OrderStatus::Paid, OrderStatus::Preparing];

    /** Durée max du verrou applicatif : appel Chronopost (15 s) + marge. */
    private const LOCK_SECONDS = 60;

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

    /** Verrou partagé par la génération et l'annulation d'étiquette d'une commande. */
    public static function lockFor(Order $order): Lock
    {
        return Cache::lock("shipping-label:order:{$order->id}", self::LOCK_SECONDS);
    }

    /**
     * @throws ShippingLabelFailed commande non éligible, opération déjà en cours, ou refus/panne Chronopost
     */
    public function execute(Order $order, ?int $adminId): ShippingLabel
    {
        $lock = self::lockFor($order);
        if (! $lock->get()) {
            throw new ShippingLabelFailed('Une opération sur l\'étiquette de cette commande est déjà en cours : patientez quelques secondes.');
        }

        try {
            $fresh = Order::query()->findOrFail($order->id);
            $existing = $fresh->activeShippingLabel()->first();
            if ($existing !== null) {
                return $existing;
            }

            $this->assertEligible($fresh);
            $weight = (int) $fresh->items()->sum(DB::raw('weight_g * quantity'));

            $created = $this->client->create($fresh, $weight);

            return $this->store($fresh, $created, $weight, $adminId);
        } finally {
            $lock->release();
        }
    }

    /** Enregistre l'étiquette ; en cas d'échec, ne laisse ni fichier ni LT orphelins. */
    private function store(Order $order, CreatedLabel $created, int $weight, ?int $adminId): ShippingLabel
    {
        $path = "shipping-labels/{$order->id}/{$created->skybillNumber}.pdf";

        try {
            Storage::disk('local')->put($path, $created->pdf);

            return $order->shippingLabels()->create([
                'skybill_number' => $created->skybillNumber,
                'reservation_number' => $created->reservationNumber,
                'pdf_path' => $path,
                'weight_g' => $weight,
                'created_by_admin_id' => $adminId,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            Log::error('Chronopost : étiquette créée mais non enregistrée, annulation de la LT.', ['order_id' => $order->id, 'skybill' => $created->skybillNumber, 'error' => $e->getMessage()]);

            try {
                $this->client->cancel($created->skybillNumber, $order);
            } catch (ShippingLabelFailed) {
                Log::critical('Chronopost : LT orpheline à annuler manuellement.', ['order_id' => $order->id, 'skybill' => $created->skybillNumber]);
            }

            throw new ShippingLabelFailed('L\'étiquette n\'a pas pu être enregistrée : réessayez.', previous: $e);
        }
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
