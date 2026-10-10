<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PickupPointResource\Pages;
use App\Models\PickupPoint;
use App\Services\Geo\AddressGeocoder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Points de retrait chez des commerçants partenaires (T27-L7a). L'adresse est
 * géocodée à l'enregistrement ; sans coordonnées, le point n'est pas proposable.
 */
class PickupPointResource extends Resource
{
    protected static ?string $model = PickupPoint::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Points de retrait';

    protected static ?string $modelLabel = 'point de retrait';

    protected static ?string $pluralModelLabel = 'Points de retrait';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('Nom du commerçant')->required()->maxLength(255),
            TextInput::make('address_line1')->label('Adresse')->required()->maxLength(255),
            TextInput::make('postal_code')->label('Code postal')->required()->maxLength(10),
            TextInput::make('city')->label('Ville')->required()->maxLength(255),
            Textarea::make('opening_hours')->label('Horaires d\'ouverture')->required()->rows(3)->maxLength(2000),
            Textarea::make('instructions')->label('Instructions de retrait')->rows(3)->maxLength(2000),
            Toggle::make('is_active')->label('Actif')->default(true),
            TextInput::make('position')->label('Position')->numeric()->minValue(0)->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('city')->label('Ville')->searchable(),
                TextColumn::make('postal_code')->label('Code postal'),
                IconColumn::make('geocoded')
                    ->label('Géocodé')
                    ->boolean()
                    ->state(fn (PickupPoint $record): bool => $record->isGeocoded()),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
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

    /**
     * Ajoute latitude/longitude aux données du formulaire ; avertit si l'adresse est introuvable.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withCoordinates(array $data): array
    {
        $address = trim(($data['address_line1'] ?? '').' '.($data['postal_code'] ?? '').' '.($data['city'] ?? ''));
        $coordinates = app(AddressGeocoder::class)->geocode($address, (string) ($data['postal_code'] ?? ''));

        $data['latitude'] = $coordinates['lat'] ?? null;
        $data['longitude'] = $coordinates['lng'] ?? null;

        if ($coordinates === null) {
            Notification::make()
                ->warning()
                ->title('Adresse introuvable, vérifiez-la')
                ->body('Ce point de retrait ne sera pas proposé aux clients tant que son adresse n\'est pas reconnue.')
                ->send();
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPickupPoints::route('/'),
            'create' => Pages\CreatePickupPoint::route('/create'),
            'edit' => Pages\EditPickupPoint::route('/{record}/edit'),
        ];
    }
}
