<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Models\Admin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Ressource BO « Administrateurs » (PLAN.md §14, T17). Pas de 2FA
 * (reporté en V2, voir docs/DECISIONS.md) : juste guard `admin` séparé +
 * rate limiting (T00/T03). Un admin ne peut pas se supprimer lui-même
 * (évite un verrouillage accidentel du back-office).
 */
class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Administrateurs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('password')
                ->label('Mot de passe')
                ->password()
                ->revealable()
                ->minLength(12)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Laisser vide pour ne pas changer le mot de passe actuel.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('created_at')->label('Créé le')->date('d/m/Y'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Admin $record, DeleteAction $action): void {
                        if ($record->id === auth('admin')->id()) {
                            Notification::make()
                                ->title('Vous ne pouvez pas supprimer votre propre compte.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
