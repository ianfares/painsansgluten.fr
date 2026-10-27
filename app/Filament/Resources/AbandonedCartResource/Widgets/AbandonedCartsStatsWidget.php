<?php

declare(strict_types=1);

namespace App\Filament\Resources\AbandonedCartResource\Widgets;

use App\Services\Analytics\AbandonedCartReport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Chiffres 7 j / 30 j de la page « Paniers abandonnés ».
 */
class AbandonedCartsStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $report = app(AbandonedCartReport::class);
        $week = $report->stats(7);
        $month = $report->stats(30);
        $euros = fn (int $cents): string => number_format($cents / 100, 2, ',', ' ').' €';
        $share = fn (array $s): string => $s['abandoned'] > 0 ? round($s['below_minimum'] * 100 / $s['abandoned']).' %' : '—';

        return [
            Stat::make('Paniers abandonnés', "{$week['abandoned']} (7 j) · {$month['abandoned']} (30 j)"),
            Stat::make('Commandes en ligne', "{$week['orders']} (7 j) · {$month['orders']} (30 j)"),
            Stat::make('Abandonnés sous le minimum', $share($week).' (7 j) · '.$share($month).' (30 j)')
                ->description('Minimum de commande : '.$euros($report->minimumTtc())),
            Stat::make('Montant moyen abandonné', $euros($week['average_ttc']).' (7 j) · '.$euros($month['average_ttc']).' (30 j)'),
            Stat::make('Refus « minimum non atteint »', "{$week['blocks']} (7 j) · {$month['blocks']} (30 j)")
                ->description("Ensuite commandé : {$week['blocks_then_ordered']} (7 j) · {$month['blocks_then_ordered']} (30 j)"),
        ];
    }
}
