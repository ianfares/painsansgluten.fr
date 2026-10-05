<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Exceptions\Shipping\ShippingNotConfigured;
use App\Models\ClosedDate;
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
     * @throws ShippingNotConfigured si aucun jour d'expédition ou aucune
     *                               heure limite n'est renseigné(e).
     */
    public function forInstant(CarbonImmutable $at): CarbonImmutable
    {
        $weekdays = $this->settings->shipping_weekdays;
        $cutoff = $this->settings->order_cutoff_time;
        $leadDays = $this->settings->production_lead_days;

        if ($weekdays === [] || blank($cutoff) || $leadDays === null) {
            throw new ShippingNotConfigured('Les paramètres de date d\'expédition ne sont pas renseignés.');
        }

        $at = $at->setTimezone('Europe/Paris');

        [$cutoffHour, $cutoffMinute] = array_map('intval', explode(':', $cutoff));
        $cutoffToday = $at->setTime($cutoffHour, $cutoffMinute, 0);

        $date = $at->greaterThan($cutoffToday) ? $at->addDay()->startOfDay() : $at->startOfDay();

        $remainingLeadDays = $leadDays;
        $steps = 0;
        while ($remainingLeadDays > 0) {
            $date = $this->nextDay($date, $steps);
            if (! $this->isClosed($date)) {
                $remainingLeadDays--;
            }
        }

        while (! $this->isShippingDay($date, $weekdays) || $this->isClosed($date)) {
            $date = $this->nextDay($date, $steps);
        }

        return $date;
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

    private function isClosed(CarbonImmutable $date): bool
    {
        return ClosedDate::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }
}
