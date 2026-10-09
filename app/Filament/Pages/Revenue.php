<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Revenue\Widgets\RevenueByCategoryChart;
use App\Filament\Revenue\Widgets\RevenueByPaymentMethodChart;
use App\Filament\Revenue\Widgets\RevenueStats;
use App\Filament\Revenue\Widgets\RevenueTimelineChart;
use App\Filament\Revenue\Widgets\TopProductsChart;
use App\Services\Reporting\RevenueReport;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Page « Recettes » (T26, demande d'Ian du 09/10/2026) : chiffres clés et
 * graphiques sur une période au choix. Calculs : App\Services\Reporting\RevenueReport.
 * Widgets rangés hors de app/Filament/Widgets pour ne pas apparaître sur le
 * tableau de bord principal.
 */
class Revenue extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'recettes';

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Comptabilité';

    protected static ?string $navigationLabel = 'Recettes';

    protected static ?string $title = 'Recettes';

    protected static ?int $navigationSort = 0;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('period')
                ->label('Période')
                ->options(RevenueReport::PERIODS)
                ->default(RevenueReport::DEFAULT_PERIOD)
                ->selectablePlaceholder(false),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            RevenueStats::class,
            RevenueTimelineChart::class,
            RevenueByCategoryChart::class,
            RevenueByPaymentMethodChart::class,
            TopProductsChart::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
