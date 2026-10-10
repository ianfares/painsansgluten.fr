<?php

declare(strict_types=1);

namespace App\Filament\Resources\PickupPointResource\Pages;

use App\Filament\Resources\PickupPointResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePickupPoint extends CreateRecord
{
    protected static string $resource = PickupPointResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PickupPointResource::withCoordinates($data);
    }
}
