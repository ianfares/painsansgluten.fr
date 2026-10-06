<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Ressource BO « Clients » (PLAN.md §14, T17) : recherche, détail,
 * commandes, adresse — **jamais de mot de passe visible** (le modèle
 * `User` le masque déjà, `#[Hidden]`). Pas de création/édition : les
 * clients gèrent leur propre compte via l'espace client (T18/Fortify).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Clients';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('last_name')->label('Nom'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->label('Téléphone'),
                TextColumn::make('orders_count')->label('Commandes')->counts('orders'),
                IconColumn::make('deletion_requested_at')
                    ->label('Suppression demandée')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->deletion_requested_at !== null),
                TextColumn::make('created_at')->label('Inscrit le')->date('d/m/Y'),
            ])
            ->filters([
                TernaryFilter::make('deletion_requested')
                    ->label('Suppression demandée')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('deletion_requested_at'),
                        false: fn ($query) => $query->whereNull('deletion_requested_at'),
                    ),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Identité')
                ->columns(2)
                ->schema([
                    TextEntry::make('first_name')->label('Prénom'),
                    TextEntry::make('last_name')->label('Nom'),
                    TextEntry::make('email'),
                    TextEntry::make('phone')->label('Téléphone'),
                    TextEntry::make('email_verified_at')->label('Email vérifié le')->dateTime('d/m/Y H:i')->placeholder('Non vérifié'),
                    TextEntry::make('deletion_requested_at')->label('Suppression demandée le')->dateTime('d/m/Y H:i')->placeholder('—'),
                ]),
            InfolistSection::make('Adresses')
                ->schema([
                    RepeatableEntry::make('addresses')
                        ->label('')
                        ->schema([
                            TextEntry::make('line1')->label('Adresse'),
                            TextEntry::make('postal_code')->label('Code postal'),
                            TextEntry::make('city')->label('Ville'),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
