<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingRateResource\Pages;
use App\Models\ShippingRate;
use App\Settings\ShippingSettings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Grille des frais de port (PLAN.md §8.3). Tant que cette grille est vide,
 * le tunnel de commande doit rester bloqué (règle appliquée en T10/T13).
 */
class ShippingRateResource extends Resource
{
    protected static ?string $model = ShippingRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Frais de port';

    protected static ?string $modelLabel = 'tranche de frais de port';

    protected static ?string $pluralModelLabel = 'Frais de port';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('min_weight_g')->label('Poids minimum (g)')->numeric()->required()->minValue(0),
            TextInput::make('max_weight_g')->label('Poids maximum (g)')->numeric()->required()->gte('min_weight_g'),
            TextInput::make('price_ht')
                ->label('Prix HT (centimes)')
                ->numeric()->required()->minValue(0)
                ->helperText('Prix HT de la grille transporteur, en centimes (ex. 834 pour 8,34 €). Le client paie ce prix + la TVA du port (Paramètres → Expédition).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('min_weight_g')->label('De (g)')->sortable(),
                TextColumn::make('max_weight_g')->label('À (g)')->sortable(),
                TextColumn::make('price_ht')->label('Prix HT')->money('EUR', divideBy: 100)->sortable(),
                TextColumn::make('price_ttc')
                    ->label('Prix TTC client')
                    ->state(fn (ShippingRate $record): int => $record->priceTtc((float) (app(ShippingSettings::class)->shipping_vat_rate ?? 0)))
                    ->money('EUR', divideBy: 100),
            ])
            ->defaultSort('min_weight_g')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingRates::route('/'),
            'create' => Pages\CreateShippingRate::route('/create'),
            'edit' => Pages\EditShippingRate::route('/{record}/edit'),
        ];
    }
}
