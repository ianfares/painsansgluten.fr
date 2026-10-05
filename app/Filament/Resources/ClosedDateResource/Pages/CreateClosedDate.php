<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClosedDateResource\Pages;

use App\Filament\Resources\ClosedDateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClosedDate extends CreateRecord
{
    protected static string $resource = ClosedDateResource::class;
}
