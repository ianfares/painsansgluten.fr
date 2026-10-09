<?php

declare(strict_types=1);

namespace App\Filament\Revenue\Widgets;

use App\Services\Reporting\RevenueReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $summary = RevenueReport::for($this->filters['period'] ?? null)->summary();
        $euros = fn (int $cents) => number_format($cents / 100, 2, ',', ' ').' €';

        return [
            Stat::make('Recette TTC', $euros($summary['ttc']))->description('Factures moins avoirs'),
            Stat::make('Recette HT', $euros($summary['ht'])),
            Stat::make('Commandes', (string) $summary['orders']),
            Stat::make('Panier moyen TTC', $euros($summary['average_basket'])),
        ];
    }
}
