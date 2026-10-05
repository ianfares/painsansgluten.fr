<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClosedDateResource\Pages;
use App\Models\ClosedDate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Jours/périodes de fermeture (congés, fériés) utilisés par le calcul de
 * la date d'expédition (PLAN.md §8.4).
 */
class ClosedDateResource extends Resource
{
    protected static ?string $model = ClosedDate::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Jours fermés';

    protected static ?string $modelLabel = 'période de fermeture';

    protected static ?string $pluralModelLabel = 'Jours fermés';

    public static function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('start_date')->label('Du')->required()->native(false),
            DatePicker::make('end_date')->label('Au')->required()->native(false)->afterOrEqual('start_date'),
            TextInput::make('label')->label('Libellé')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_date')->label('Du')->date('d/m/Y')->sortable(),
                TextColumn::make('end_date')->label('Au')->date('d/m/Y')->sortable(),
                TextColumn::make('label')->label('Libellé')->searchable(),
            ])
            ->defaultSort('start_date')
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
            'index' => Pages\ListClosedDates::route('/'),
            'create' => Pages\CreateClosedDate::route('/create'),
            'edit' => Pages\EditClosedDate::route('/{record}/edit'),
        ];
    }
}
