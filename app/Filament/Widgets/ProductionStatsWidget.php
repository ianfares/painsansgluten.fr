<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\ProductionToDo;
use App\Services\Reporting\ProductionPlan;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget compact « À produire » du tableau de bord (T27-L9), avec lien vers la page.
 */
class ProductionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = CarbonImmutable::today();
        $url = ProductionToDo::getUrl();

        $day = (new ProductionPlan($today, $today))->totalsByProduct()->sum('quantity');
        $week = (new ProductionPlan($today, $today->addDays(6)))->totalsByProduct()->sum('quantity');

        return [
            Stat::make("À produire aujourd'hui", (string) $day)->description('unités — voir le détail')->url($url),
            Stat::make('À produire cette semaine', (string) $week)->description('unités sur 7 jours — voir le détail')->url($url),
        ];
    }
}
