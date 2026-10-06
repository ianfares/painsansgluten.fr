<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Orders\ValidateBankTransferPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\BankTransferValidationNotAllowed;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Vue BO des commandes (PLAN.md §14). **Périmètre volontairement réduit à
 * ce dont T15 a besoin** (liste, filtres statut/paiement, filtre « Virements
 * en attente », action « Valider le virement reçu ») — recherche par
 * nom/email, onglet Expéditions, détail complet, etc. relèvent de **T17**
 * et restent à construire (voir docs/DECISIONS.md, T15).
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
