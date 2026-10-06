<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Commandes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('N°'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label()),
                TextColumn::make('total_ttc')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €'),
                TextColumn::make('created_at')->label('Créée le')->date('d/m/Y'),
            ])
            ->headerActions([])
            ->actions([
                Action::make('view')
                    ->label('Voir')
                    ->url(fn (Order $record): string => route('filament.admin.resources.orders.view', $record))
                    ->icon('heroicon-o-eye'),
            ]);
    }
}
