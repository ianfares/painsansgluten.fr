<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\InvoiceType;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

/**
 * Ressource BO « Factures & avoirs » (PLAN.md §12, §14, T16). Lecture seule
 * (aucune facture ne se modifie — CLAUDE.md, « facture émise = immuable ») :
 * pas de création, pas d'édition, juste la liste et le téléchargement.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Factures & avoirs';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Numéro')->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceType $state): string => $state->label()),
                TextColumn::make('order.number')->label('N° commande')->searchable(),
                TextColumn::make('order')
                    ->label('Client')
                    ->formatStateUsing(fn (Invoice $record): string => "{$record->order->first_name} {$record->order->last_name}"),
                TextColumn::make('total_ttc')
                    ->label('Total TTC')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2, ',', ' ').' €'),
                TextColumn::make('issued_at')->label('Émise le')->date('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(array_combine(
                        array_map(fn (InvoiceType $t) => $t->value, InvoiceType::cases()),
                        array_map(fn (InvoiceType $t) => $t->label(), InvoiceType::cases()),
                    )),
                Filter::make('issued_at')
                    ->form([
                        DatePicker::make('from')->label('Émise à partir du'),
                        DatePicker::make('to')->label('Jusqu\'au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('issued_at', '>=', $date))
                            ->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('issued_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('download')
                    ->label('Télécharger le PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Invoice $record): string => URL::temporarySignedRoute(
                        'invoices.download',
                        now()->addMinutes(10),
                        ['invoice' => $record],
                    ))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
