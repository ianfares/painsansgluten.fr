<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClosedDateResource\Pages;

use App\Filament\Resources\ClosedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClosedDate extends EditRecord
{
    protected static string $resource = ClosedDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
