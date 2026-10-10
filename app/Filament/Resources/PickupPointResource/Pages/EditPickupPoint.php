<?php

declare(strict_types=1);

namespace App\Filament\Resources\PickupPointResource\Pages;

use App\Filament\Resources\PickupPointResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPickupPoint extends EditRecord
{
    protected static string $resource = PickupPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PickupPointResource::withCoordinates($data);
    }
}
