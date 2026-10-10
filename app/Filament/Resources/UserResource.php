<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\AccountType;
use App\Enums\ProStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Models\User;
use App\Rules\Siret;
use App\Services\Pricing\CustomerPricing;
use Closure;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Ressource BO « Clients » (PLAN.md §14, T17) : recherche, détail,
 * commandes, adresse — **jamais de mot de passe visible** (le modèle
 * `User` le masque déjà, `#[Hidden]`). T27-L5 : création manuelle et
 * édition (sauf email et mot de passe : le client les gère lui-même),
 * validation pro, désactivation / réactivation. Jamais de suppression.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Clients';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Client')
                ->columns(2)
                ->schema([
                    Select::make('account_type')
                        ->label('Type de compte')
                        ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $t) => [$t->value => $t->label()]))
                        ->default(AccountType::Individual->value)
                        ->required()
                        ->live()
                        ->selectablePlaceholder(false),
                    TextInput::make('first_name')->label('Prénom')->required()->maxLength(255),
                    TextInput::make('last_name')->label('Nom')->required()->maxLength(255),
                    TextInput::make('phone')->label('Téléphone')->required()->maxLength(30),
                    // Email : saisi à la création seulement (le client change le sien lui-même, avec revérification).
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(User::class, 'email', ignoreRecord: true)
                        ->disabled(fn (string $operation): bool => $operation !== 'create')
                        ->dehydrated(fn (string $operation): bool => $operation === 'create')
                        ->helperText(fn (string $operation): ?string => $operation === 'create'
                            ? 'Le client recevra un email pour choisir son mot de passe.'
                            : null),
                    TextInput::make('company_name')
                        ->label('Raison sociale')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('account_type') === AccountType::Pro->value)
                        ->required(fn (Get $get): bool => $get('account_type') === AccountType::Pro->value),
                    TextInput::make('siret')
                        ->label('SIRET')
                        ->maxLength(20)
                        ->visible(fn (Get $get): bool => $get('account_type') === AccountType::Pro->value)
                        ->required(fn (Get $get): bool => $get('account_type') === AccountType::Pro->value)
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : preg_replace('/[\s.]/', '', $state))
                        ->rules([fn (): Closure => fn (string $attribute, mixed $value, Closure $fail) => (new Siret)->validate($attribute, preg_replace('/[\s.]/', '', (string) $value), $fail)]),
                    TextInput::make('discount_percent')
                        ->label('Remise client (%)')
                        ->helperText('Sur les produits uniquement, pas sur la livraison. Appliquée dès que le client est connecté (pro : une fois validé). 0 = aucune remise.')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(CustomerPricing::MAX_PERCENT)
                        ->default(0)
                        ->required()
                        ->suffix('%'),
                    Toggle::make('lab_pickup_allowed')
                        ->label('Retrait au laboratoire autorisé')
                        ->helperText('Réservé aux comptes pro validés.')
                        ->disabled(fn (?User $record): bool => $record === null || ! $record->isApprovedPro())
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                ]),
        ]);
    }

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
                TextColumn::make('account_type')->label('Type')->badge()->formatStateUsing(fn (AccountType $state): string => $state->label()),
                TextColumn::make('pro_status')->label('Statut pro')->badge()
                    ->formatStateUsing(fn (?ProStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?ProStatus $state): string => $state?->color() ?? 'gray'),
                IconColumn::make('deactivated_at')
                    ->label('Désactivé')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->deactivated_at !== null),
                TextColumn::make('orders_count')->label('Commandes')->counts('orders'),
                IconColumn::make('deletion_requested_at')
                    ->label('Suppression demandée')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->deletion_requested_at !== null),
                TextColumn::make('created_at')->label('Inscrit le')->date('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->label('Type de compte')
                    ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $t) => [$t->value => $t->label()])),
                SelectFilter::make('pro_status')
                    ->label('Statut pro')
                    ->options(collect(ProStatus::cases())->mapWithKeys(fn (ProStatus $s) => [$s->value => $s->label()])),
                TernaryFilter::make('deactivated')
                    ->label('Désactivé')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('deactivated_at'),
                        false: fn ($query) => $query->whereNull('deactivated_at'),
                    ),
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
                    TextEntry::make('account_type')->label('Type de compte')->formatStateUsing(fn (AccountType $state): string => $state->label()),
                    TextEntry::make('company_name')->label('Raison sociale')->placeholder('—'),
                    TextEntry::make('siret')->label('SIRET')->placeholder('—'),
                    TextEntry::make('pro_status')->label('Statut pro')->formatStateUsing(fn (?ProStatus $state): string => $state?->label() ?? '—'),
                    TextEntry::make('pro_approved_at')->label('Pro validé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('discount_percent')->label('Remise client')->formatStateUsing(fn (int $state): string => $state > 0 ? "{$state} %" : 'Aucune'),
                    TextEntry::make('lab_pickup_allowed')->label('Retrait au laboratoire')->formatStateUsing(fn (bool $state): string => $state ? 'Autorisé' : 'Non autorisé'),
                    TextEntry::make('deactivated_at')->label('Désactivé le')->dateTime('d/m/Y H:i')->placeholder('—'),
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
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /** Aucune suppression depuis l'admin : on désactive (les données sont conservées). */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
