<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Vue BO « Expéditions » (PLAN.md §14, T17) : commandes `paid`/`preparing`
 * triées par date d'expédition prévue, avec mise en évidence des commandes
 * du jour et en retard.
 */
class Expeditions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Expéditions';

    protected static string $view = 'filament.pages.expeditions';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing]))
            ->defaultSort('planned_ship_date')
            ->columns([
                TextColumn::make('number')->label('N°'),
                TextColumn::make('planned_ship_date')
                    ->label('Expédition prévue')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(function (Order $record): string {
                        if ($record->planned_ship_date === null) {
                            return 'gray';
                        }

                        return match (true) {
                            $record->planned_ship_date->isToday() => 'warning',
                            $record->planned_ship_date->isPast() => 'danger',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label()),
                TextColumn::make('first_name')
                    ->label('Client')
                    ->formatStateUsing(fn (Order $record): string => "{$record->first_name} {$record->last_name}"),
                TextColumn::make('relay_name')->label('Relais'),
            ]);
    }
}
