<?php

declare(strict_types=1);

namespace App\Filament\Revenue\Widgets;

use App\Services\Reporting\RevenueReport;

class RevenueByCategoryChart extends RevenueChart
{
    protected static ?string $heading = 'Par catégorie';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'pie';
    }

    protected function values(RevenueReport $report): array
    {
        return $report->byCategory();
    }
}
