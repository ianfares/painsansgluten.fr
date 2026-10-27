<?php

declare(strict_types=1);

namespace App\Filament\Resources\AbandonedCartResource\Pages;

use App\Filament\Resources\AbandonedCartResource;
use App\Filament\Resources\AbandonedCartResource\Widgets\AbandonedCartsStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListAbandonedCarts extends ListRecords
{
    protected static string $resource = AbandonedCartResource::class;

    protected static ?string $title = 'Paniers abandonnés';

    protected ?string $subheading = 'Paniers remplis sans commande depuis plus d\'une heure. Montants au prix catalogue TTC, hors port et hors remise pro. Les paniers des visiteurs sont effacés après 30 jours.';

    protected function getHeaderWidgets(): array
    {
        return [AbandonedCartsStatsWidget::class];
    }
}
