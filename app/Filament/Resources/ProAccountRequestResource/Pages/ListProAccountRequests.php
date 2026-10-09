<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProAccountRequestResource\Pages;

use App\Filament\Resources\ProAccountRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListProAccountRequests extends ListRecords
{
    protected static string $resource = ProAccountRequestResource::class;
}
