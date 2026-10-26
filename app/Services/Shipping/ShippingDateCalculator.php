<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\DeliveryMethod;
use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Models\ClosedDate;
use App\Models\Order;
use App\Settings\ShippingSettings;
use Carbon\CarbonImmutable;

/**
 * Calcule la date d'expédition prévue (PLAN.md §8.4). L'instant de
 * référence est toujours injecté (jamais `now()` en dur) pour rester
 * testable ; tous les calculs se font en `Europe/Paris`.
 */
class ShippingDateCalculator
{
    /** Garde-fou anti-boucle infinie (PLAN.md §8.4). */
    private const MAX_SEARCH_DAYS = 60;

    private const WEEKDAY_NAMES = [
        0 => 'monday', 1 => 'tuesday', 2 => 'wednesday', 3 => 'thursday',
        4 => 'friday', 5 => 'saturday', 6 => 'sunday',
    ];

    public function __construct(private readonly ShippingSettings $settings) {}

    /**
     * `$pickupWeekdays` (24/10/2026) : jours de retrait d'un commerçant, qui
     * remplacent les jours d'expédition ; même heure limite, même délai de
     * fabrication, mêmes jours fermés. Vide ou null = jours d'expédition.
     *
     * @param  list<string>|null  $pickupWeekdays
     *
     * @throws ShippingNotConfigured si aucun jour d'expédition ou aucune
     *                               heure limite n'est renseigné(e).
     */
    public function forInstant(CarbonImmutable $at, ?array $pickupWeekdays = null): CarbonImmutable
    {
        $weekdays = $this->settings->shipping_weekdays;
        $cutoff = $this->settings->order_cutoff_time;
        $leadDays = $this->settings->production_lead_days;

        if ($weekdays === [] || blank($cutoff) || $leadDays === null) {
            throw new ShippingNotConfigured('Les paramètres de date d\'expédition ne sont pas renseignés.');
        }

        $weekdays = $pickupWeekdays ?: $weekdays;

        $at = $at->setTimezone('Europe/Paris');

        [$cutoffHour, $cutoffMinute] = array_map('intval', explode(':', $cutoff));
        $cutoffToday = $at->setTime($cutoffHour, $cutoffMinute, 0);

        $date = $at->greaterThan($cutoffToday) ? $at->addDay()->startOfDay() : $at->startOfDay();

        // Jours fermés chargés en UNE requête pour toute la fenêtre de recherche
        // (avant : une requête par jour testé, à chaque rendu du panier — audit du 26/10/2026).
        $closedPeriods = $this->closedPeriodsBetween($date, $date->addDays(self::MAX_SEARCH_DAYS + 1));

        $remainingLeadDays = $leadDays;
        $steps = 0;
        while ($remainingLeadDays > 0) {
            $date = $this->nextDay($date, $steps);
            if (! $this->isClosed($date, $closedPeriods)) {
                $remainingLeadDays--;
            }
        }

        while (! $this->isShippingDay($date, $weekdays) || $this->isClosed($date, $closedPeriods)) {
            $date = $this->nextDay($date, $steps);
        }

        return $date;
    }

    /**
     * Date prévue d'une commande (calcul au paiement) : jours de retrait du
     * commerçant figés dans le snapshot à la commande, sinon jours d'expédition.
     */
    public function forOrder(Order $order, CarbonImmutable $at): CarbonImmutable
    {
        $pickupWeekdays = $order->delivery_method === DeliveryMethod::MerchantPickup
            ? ($order->relay_snapshot['pickup_weekdays'] ?? null)
            : null;

        return $this->forInstant($at, is_array($pickupWeekdays) ? array_values($pickupWeekdays) : null);
    }

    public function formatFrench(CarbonImmutable $date): string
    {
        return $date->locale('fr')->isoFormat('dddd D MMMM');
    }

    private function nextDay(CarbonImmutable $date, int &$steps): CarbonImmutable
    {
        if (++$steps > self::MAX_SEARCH_DAYS) {
            throw new ShippingNotConfigured('Impossible de déterminer une date d\'expédition dans un délai raisonnable (jours fermés mal configurés ?).');
        }

        return $date->addDay();
    }

    private function isShippingDay(CarbonImmutable $date, array $weekdays): bool
    {
        return in_array(self::WEEKDAY_NAMES[$date->dayOfWeekIso - 1], $weekdays, true);
    }

    /**
     * @return list<array{0: string, 1: string}> périodes fermées [début, fin] (Y-m-d, bornes incluses)
     */
    private function closedPeriodsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return ClosedDate::query()
            ->whereDate('end_date', '>=', $from->toDateString())
            ->whereDate('start_date', '<=', $to->toDateString())
            ->get(['start_date', 'end_date'])
            ->map(fn (ClosedDate $closed): array => [
                CarbonImmutable::parse($closed->start_date)->toDateString(),
                CarbonImmutable::parse($closed->end_date)->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** @param  list<array{0: string, 1: string}>  $closedPeriods */
    private function isClosed(CarbonImmutable $date, array $closedPeriods): bool
    {
        $day = $date->toDateString();
        foreach ($closedPeriods as [$start, $end]) {
            if ($start <= $day && $day <= $end) {
                return true;
            }
        }

        return false;
    }
}
