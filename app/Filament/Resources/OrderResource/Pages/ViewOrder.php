<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\DeliveryNoteActions;
use App\Filament\Resources\OrderResource\ShippingLabelActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [DeliveryNoteActions::page(), ...ShippingLabelActions::all(Action::class), OrderResource::deleteUnpaidAction(Action::class)];
    }
}
