<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Redirections 301 depuis Shopify (PLAN.md §16.4, §23) + CRUD BO pour en
 * ajouter manuellement si de nouvelles URLs apparaissent.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-top-right-on-square';

    protected static ?string $navigationGroup = 'Contenus';

    protected static ?string $navigationLabel = 'Redirections';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('source')->label('URL source')->required()->unique(ignoreRecord: true)->maxLength(255)->placeholder('/ancienne-url'),
            TextInput::make('target')->label('URL cible')->required()->maxLength(255)->placeholder('/nouvelle-url'),
            Select::make('status_code')->label('Code')->options([301 => '301 (permanent)', 302 => '302 (temporaire)'])->default(301)->required(),
            Toggle::make('is_active')->label('Active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')->label('Source')->searchable(),
                TextColumn::make('target')->label('Cible'),
                TextColumn::make('status_code')->label('Code'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
