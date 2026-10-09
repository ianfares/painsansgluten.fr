<?php

declare(strict_types=1);

namespace App\Filament\Revenue\Widgets;

use App\Services\Reporting\RevenueReport;

class RevenueByPaymentMethodChart extends RevenueChart
{
    protected static ?string $heading = 'Par moyen de paiement';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'pie';
    }

    protected function values(RevenueReport $report): array
    {
        return $report->byPaymentMethod();
    }
}
