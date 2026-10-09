<?php

declare(strict_types=1);

namespace App\Filament\Revenue\Widgets;

use App\Services\Reporting\RevenueReport;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Base des graphiques de la page « Recettes » : montants affichés en euros,
 * couleurs de la charte (vert, ocre, puis nuances).
 */
abstract class RevenueChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected const COLORS = ['#5d6e41', '#b47c38', '#8a9a6b', '#d4a76a', '#3f4b2c', '#7a5426', '#b5c29a', '#e8cfa5', '#2a3220', '#a0896b'];

    protected static ?string $maxHeight = '300px';

    /** @return array<string, int> libellé => centimes */
    abstract protected function values(RevenueReport $report): array;

    protected function getData(): array
    {
        $values = $this->values(RevenueReport::for($this->filters['period'] ?? null));

        return [
            'datasets' => [[
                'label' => 'Recette TTC (€)',
                'data' => array_map(fn (int $cents) => round($cents / 100, 2), array_values($values)),
                'backgroundColor' => $this->getType() === 'bar' ? self::COLORS[0] : array_slice(array_merge(self::COLORS, self::COLORS), 0, max(1, count($values))),
            ]],
            'labels' => array_map('strval', array_keys($values)),
        ];
    }
}
