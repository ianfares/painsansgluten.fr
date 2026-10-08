<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Orders\RefundOrderAction;
use App\Actions\Orders\ShipOrderAction;
use App\Actions\Orders\ValidateBankTransferPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\BankTransferValidationNotAllowed;
use App\Exceptions\Orders\InvalidOrderTransition;
use App\Exceptions\Orders\RefundNotAvailable;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\Orders\OrderStateMachine;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Vue BO des commandes (PLAN.md §14, T15 puis T17) : liste, recherche,
 * filtres, détail complet (lignes, relais, historique, facture), actions de
 * transition de statut avec confirmation.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Commandes';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('N°')->searchable(),
                TextColumn::make('first_name')
                    ->label('Client')
                    ->formatStateUsing(fn (Order $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label()),
                TextColumn::make('payment_method')
                    ->label('Paiement')
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('total_ttc')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €'),
                TextColumn::make('planned_ship_date')
                    ->label('Expédition prévue')
                    ->date('d/m/Y'),
                TextColumn::make('relay_name')->label('Relais')->toggleable(),
                TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(array_combine(
                        array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
                        array_map(fn (OrderStatus $s) => $s->label(), OrderStatus::cases()),
                    )),
                SelectFilter::make('payment_method')
                    ->label('Paiement')
                    ->options(array_combine(
                        array_map(fn (PaymentMethod $p) => $p->value, PaymentMethod::cases()),
                        array_map(fn (PaymentMethod $p) => $p->label(), PaymentMethod::cases()),
                    )),
                Filter::make('bank_transfer_pending')
                    ->label('Virements en attente')
                    ->toggle()
                    ->query(fn ($query) => $query
                        ->where('payment_method', PaymentMethod::BankTransfer)
                        ->where('status', OrderStatus::PendingPayment)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('validateBankTransfer')
                    ->label('Valider le virement reçu')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->payment_method === PaymentMethod::BankTransfer
                        && $record->status === OrderStatus::PendingPayment)
                    ->requiresConfirmation()
                    ->modalHeading('Valider le virement reçu')
                    ->modalDescription(function (Order $record): string {
                        $total = number_format($record->total_ttc / 100, 2, ',', ' ').' €';

                        return "Vérifiez sur le relevé bancaire que le montant ({$total}) et la référence ({$record->number}) ".
                            'correspondent exactement avant de confirmer. Cette action déclenche la facture et un email à la cliente.';
                    })
                    ->modalSubmitActionLabel('Confirmer la validation')
                    ->action(function (Order $record, ValidateBankTransferPaymentAction $validateBankTransferPaymentAction): void {
                        try {
                            $validateBankTransferPaymentAction->execute($record, auth('admin')->id());

                            Notification::make()
                                ->title('Virement validé, commande marquée payée.')
                                ->success()
                                ->send();
                        } catch (BankTransferValidationNotAllowed $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('prepare')
                    ->label('Marquer en préparation')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Paid)
                    ->requiresConfirmation()
                    ->action(fn (Order $record, OrderStateMachine $orderStateMachine) => self::transitionWithNotification(
                        fn () => $orderStateMachine->transition($record, OrderStatus::Preparing, 'admin', auth('admin')->id()),
                        'Commande passée en préparation.',
                    )),
                Tables\Actions\Action::make('ship')
                    ->label('Expédier')
                    ->icon('heroicon-o-truck')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Preparing)
                    ->form([
                        TextInput::make('tracking_number')->label('N° de suivi Chronopost')->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Order $record, array $data, ShipOrderAction $shipOrderAction): void {
                        self::transitionWithNotification(
                            fn () => $shipOrderAction->execute($record, $data['tracking_number'], auth('admin')->id()),
                            'Commande expédiée.',
                        );
                    }),
                Tables\Actions\Action::make('deliver')
                    ->label('Marquer livrée')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Shipped)
                    ->requiresConfirmation()
                    ->action(fn (Order $record, OrderStateMachine $orderStateMachine) => self::transitionWithNotification(
                        fn () => $orderStateMachine->transition($record, OrderStatus::Delivered, 'admin', auth('admin')->id()),
                        'Commande marquée livrée.',
                    )),
                Tables\Actions\Action::make('refund')
                    ->label('Rembourser')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Order $record): string => $record->payment_method === PaymentMethod::Stripe
                        ? 'Le montant total sera remboursé sur la carte du client via Stripe, et l\'avoir sera généré.'
                        : 'Le virement doit être remboursé manuellement par vous (banque). Cette action enregistre seulement le remboursement et génère l\'avoir.')
                    ->action(function (Order $record, RefundOrderAction $refundOrderAction): void {
                        try {
                            $refundOrderAction->execute($record, auth('admin')->id());

                            Notification::make()->title('Commande remboursée, avoir généré.')->success()->send();
                        } catch (RefundNotAvailable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    private static function transitionWithNotification(callable $transition, string $successMessage): void
    {
        try {
            $transition();

            Notification::make()->title($successMessage)->success()->send();
        } catch (InvalidOrderTransition $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Commande')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->label('N°'),
                    TextEntry::make('status')->label('Statut')->badge()->formatStateUsing(fn (OrderStatus $state): string => $state->label()),
                    TextEntry::make('payment_method')->label('Paiement')->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                    TextEntry::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i'),
                    TextEntry::make('planned_ship_date')->label('Expédition prévue')->date('d/m/Y'),
                    TextEntry::make('tracking_number')->label('N° de suivi')->placeholder('—'),
                ]),
            InfolistSection::make('Client')
                ->columns(2)
                ->schema([
                    TextEntry::make('first_name')->label('Prénom'),
                    TextEntry::make('last_name')->label('Nom'),
                    TextEntry::make('email'),
                    TextEntry::make('phone')->label('Téléphone'),
                ]),
            InfolistSection::make('Facturation')
                ->columns(2)
                ->schema([
                    TextEntry::make('billing_line1')->label('Adresse'),
                    TextEntry::make('billing_postal_code')->label('Code postal'),
                    TextEntry::make('billing_city')->label('Ville'),
                ]),
            InfolistSection::make('Relais')
                ->columns(2)
                ->schema([
                    TextEntry::make('relay_name')->label('Nom du relais'),
                    TextEntry::make('relay_id')->label('Identifiant')
                        ->helperText(fn (Order $record): ?string => str_starts_with((string) $record->relay_id, 'MANUEL-')
                            ? 'Saisie manuelle provisoire (T11 non livrée) — à confirmer avant expédition.'
                            : null),
                ]),
            InfolistSection::make('Lignes')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            TextEntry::make('product_name')->label('Produit'),
                            TextEntry::make('quantity')->label('Qté'),
                            TextEntry::make('line_total_ttc')->label('Total TTC')
                                ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €'),
                        ])
                        ->columns(3),
                ]),
            InfolistSection::make('Totaux')
                ->columns(3)
                ->schema([
                    TextEntry::make('subtotal_ttc')->label('Sous-total TTC')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €'),
                    TextEntry::make('shipping_ttc')->label('Frais de port')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €'),
                    TextEntry::make('total_ttc')->label('Total TTC')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €'),
                ]),
            InfolistSection::make('Historique')
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->label('')
                        ->schema([
                            TextEntry::make('to')->label('Statut'),
                            TextEntry::make('actor_type')->label('Par'),
                            TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i'),
                            TextEntry::make('comment')->label('Commentaire')->placeholder('—'),
                        ])
                        ->columns(4),
                ]),
            InfolistSection::make('Facture')
                ->schema([
                    TextEntry::make('invoices.number')
                        ->label('Numéro(s)')
                        ->placeholder('Aucune facture émise pour le moment (émise au passage à « payée »).'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
