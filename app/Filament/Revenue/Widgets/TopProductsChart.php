<?php

declare(strict_types=1);

namespace App\Filament\Revenue\Widgets;

use App\Services\Reporting\RevenueReport;

class TopProductsChart extends RevenueChart
{
    protected static ?string $heading = 'Top 10 des produits';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function values(RevenueReport $report): array
    {
        return $report->topProducts(10);
    }
}
