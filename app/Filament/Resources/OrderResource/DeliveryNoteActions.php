<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource;

use App\Models\Order;
use App\Services\Documents\DeliveryNotePdf;
use Filament\Actions\Action as PageAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions « Bon de livraison (PDF) » (T27-L8) : ligne de liste, fiche commande
 * et action de masse. PDF généré à la volée, jamais stocké.
 */
class DeliveryNoteActions
{
    public static function row(): TableAction
    {
        return TableAction::make('deliveryNote')
            ->label('Bon de livraison (PDF)')
            ->icon('heroicon-o-document-text')
            ->visible(fn (Order $record): bool => DeliveryNotePdf::isAvailableFor($record))
            ->action(fn (Order $record): StreamedResponse => self::download([$record], 'bon-de-livraison-'.$record->number));
    }

    public static function page(): PageAction
    {
        return PageAction::make('deliveryNote')
            ->label('Bon de livraison (PDF)')
            ->icon('heroicon-o-document-text')
            ->visible(fn (Order $record): bool => DeliveryNotePdf::isAvailableFor($record))
            ->action(fn (Order $record): StreamedResponse => self::download([$record], 'bon-de-livraison-'.$record->number));
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('deliveryNotes')
            ->label('Bons de livraison (PDF)')
            ->icon('heroicon-o-document-text')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): ?StreamedResponse {
                $eligible = $records->whereInstanceOf(Order::class)
                    ->filter(fn (Order $order): bool => DeliveryNotePdf::isAvailableFor($order));

                if ($eligible->isEmpty()) {
                    Notification::make()->title('Aucune commande payée dans la sélection.')->warning()->send();

                    return null;
                }

                if ($eligible->count() < $records->count()) {
                    Notification::make()->title('Les commandes non payées ont été ignorées.')->warning()->send();
                }

                return self::download($eligible->all(), 'bons-de-livraison');
            });
    }

    /**
     * @param  iterable<Order>  $orders
     */
    private static function download(iterable $orders, string $name): StreamedResponse
    {
        $pdf = app(DeliveryNotePdf::class)->render($orders);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, "{$name}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
