<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProRequestStatus;
use App\Filament\Resources\ProAccountRequestResource\Pages;
use App\Models\ProAccountRequest;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Ressource BO « Demandes pro » (T26 B1) : liste, filtre par statut, détail
 * en lecture seule. Approuver / Refuser se font depuis la fiche. Pas de
 * création ni de modification depuis le BO.
 */
class ProAccountRequestResource extends Resource
{
    protected static ?string $model = ProAccountRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Demandes pro';

    protected static ?string $modelLabel = 'demande pro';

    protected static ?string $pluralModelLabel = 'demandes pro';

    public static function getNavigationBadge(): ?string
    {
        $pending = ProAccountRequest::query()->where('status', ProRequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Reçue le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('company_name')->label('Société')->searchable()->placeholder('—'),
                TextColumn::make('activity_type')->label('Activité')->placeholder('—'),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (ProRequestStatus $state): string => $state->label())
                    ->color(fn (ProRequestStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(array_combine(
                        array_map(fn (ProRequestStatus $s) => $s->value, ProRequestStatus::cases()),
                        array_map(fn (ProRequestStatus $s) => $s->label(), ProRequestStatus::cases()),
                    )),
            ])
            ->actions([ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Demande')
                ->columns(3)
                ->schema([
                    TextEntry::make('status')
                        ->label('Statut')
                        ->badge()
                        ->formatStateUsing(fn (ProRequestStatus $state): string => $state->label())
                        ->color(fn (ProRequestStatus $state): string => $state->color()),
                    TextEntry::make('created_at')->label('Reçue le')->dateTime('d/m/Y H:i'),
                    TextEntry::make('processed_at')->label('Traitée le')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('admin_comment')->label('Commentaire admin')->placeholder('—')->columnSpanFull(),
                ]),
            InfolistSection::make('Entreprise')
                ->columns(2)
                ->schema([
                    TextEntry::make('company_name')->label('Raison sociale')->placeholder('—'),
                    TextEntry::make('siret')->label('SIRET')->placeholder('—'),
                    TextEntry::make('activity_type')->label('Type d\'activité')->placeholder('—'),
                    TextEntry::make('activity_other')->label('Activité précisée')->placeholder('—'),
                    TextEntry::make('address_line1')->label('Adresse')->placeholder('—'),
                    TextEntry::make('postal_code')->label('Code postal')->placeholder('—'),
                    TextEntry::make('city')->label('Ville')->placeholder('—'),
                ]),
            InfolistSection::make('Contact')
                ->columns(2)
                ->schema([
                    TextEntry::make('contact_last_name')->label('Nom'),
                    TextEntry::make('contact_first_name')->label('Prénom'),
                    TextEntry::make('job_title')->label('Fonction')->placeholder('—'),
                    TextEntry::make('email'),
                    TextEntry::make('phone')->label('Téléphone'),
                    TextEntry::make('consent_at')->label('Consentement donné le')->dateTime('d/m/Y H:i'),
                ]),
            InfolistSection::make('Besoin')
                ->schema([
                    TextEntry::make('description')->label('Description du besoin')->prose()->placeholder('—'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProAccountRequests::route('/'),
            'view' => Pages\ViewProAccountRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
