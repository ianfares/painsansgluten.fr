<?php

declare(strict_types=1);

namespace App\Filament\Revenue\Widgets;

use App\Services\Reporting\RevenueReport;

class RevenueTimelineChart extends RevenueChart
{
    protected static ?string $heading = 'Recette TTC dans le temps';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function values(RevenueReport $report): array
    {
        return $report->timeline();
    }
}
