<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Analytics\AbandonedCartReport;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Paniers abandonnés (27/10/2026), lecture seule : voir AbandonedCartReport.
 * Nom affiché seulement pour un client connecté ; un visiteur reste anonyme.
 */
class AbandonedCartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Paniers abandonnés';

    protected static ?string $modelLabel = 'panier abandonné';

    protected static ?string $pluralModelLabel = 'Paniers abandonnés';

    protected static ?string $slug = 'paniers-abandonnes';

    public static function getEloquentQuery(): Builder
    {
        return app(AbandonedCartReport::class)->query()->with(['user', 'items.product']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $minimum = app(AbandonedCartReport::class)->minimumTtc();

        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('last_activity_at')->label('Dernière activité')
                    ->dateTime('d/m/Y H:i', 'Europe/Paris')->sortable(),
                TextColumn::make('client')->label('Client')
                    ->state(fn (Cart $record): string => $record->user !== null
                        ? (trim($record->user->first_name.' '.$record->user->last_name) ?: 'Client (nom non renseigné)')
                        : 'Visiteur non connecté'),
                IconColumn::make('user_id')->label('Connecté')->boolean()
                    ->state(fn (Cart $record): bool => $record->user_id !== null),
                TextColumn::make('products')->label('Produits')
                    ->state(fn (Cart $record): array => $record->items
                        ->map(fn (CartItem $item): string => $item->quantity.' × '.($item->product->name ?? 'produit supprimé'))
                        ->all())
                    ->listWithLineBreaks()->wrap(),
                TextColumn::make('subtotal_ttc')->label('Montant TTC')
                    ->formatStateUsing(fn ($state): string => number_format(((int) $state) / 100, 2, ',', ' ').' €')
                    ->sortable(),
                TextColumn::make('below_minimum')->label('Minimum')
                    ->badge()
                    ->state(fn (Cart $record): string => $minimum > 0 && (int) $record->getAttribute('subtotal_ttc') < $minimum ? 'Sous le minimum' : 'Atteint')
                    ->color(fn (string $state): string => $state === 'Sous le minimum' ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('period')->label('Période')
                    ->options(['7' => '7 derniers jours', '30' => '30 derniers jours'])
                    ->default('30')
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? app(AbandonedCartReport::class)->sinceDays($query, (int) $data['value'])
                        : $query),
                Filter::make('below_minimum')->label('Sous le minimum uniquement')
                    ->query(fn (Builder $query): Builder => app(AbandonedCartReport::class)->belowMinimum($query)),
                Filter::make('logged_in')->label('Clients connectés uniquement')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('user_id')),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbandonedCarts::route('/'),
        ];
    }
}
