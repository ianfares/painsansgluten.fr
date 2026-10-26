<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource;

use App\Actions\Shipping\CancelShippingLabelAction;
use App\Actions\Shipping\GenerateShippingLabelAction;
use App\Exceptions\Shipping\ShippingLabelFailed;
use App\Models\Order;
use Filament\Actions\Action as PageAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions « Étiquette Chronopost » (T28) pour la liste et la fiche commande :
 * générer, télécharger (PDF privé, servi uniquement dans le BO authentifié),
 * annuler. Le statut de la commande n'est pas modifié : « Expédier » reste
 * une action distincte, avec le numéro de suivi pré-rempli.
 */
class ShippingLabelActions
{
    /**
     * @param  class-string<TableAction>|class-string<PageAction>  $actionClass
     * @return list<TableAction|PageAction>
     */
    public static function all(string $actionClass): array
    {
        return [self::generate($actionClass), self::download($actionClass), self::cancel($actionClass)];
    }

    /** @param  class-string<TableAction>|class-string<PageAction>  $actionClass */
    private static function generate(string $actionClass): TableAction|PageAction
    {
        return $actionClass::make('generateShippingLabel')
            ->label('Générer l\'étiquette Chronopost')
            ->icon('heroicon-o-qr-code')
            ->visible(fn (Order $record): bool => GenerateShippingLabelAction::isAvailableFor($record) && $record->activeShippingLabel === null)
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record): string => "Le colis sera créé chez Chronopost pour le relais « {$record->relay_name} ». La commande ne change pas de statut : cliquez « Expédier » quand le colis part.")
            ->modalSubmitActionLabel('Générer')
            ->action(function (Order $record, GenerateShippingLabelAction $generate): ?StreamedResponse {
                try {
                    $label = $generate->execute($record, auth('admin')->id());
                } catch (ShippingLabelFailed $e) {
                    Notification::make()->title('Étiquette non créée')->body($e->getMessage())->danger()->persistent()->send();

                    return null;
                }

                Notification::make()->title("Étiquette créée (n° de suivi {$label->skybill_number}).")->success()->send();

                return self::stream($record);
            });
    }

    /** @param  class-string<TableAction>|class-string<PageAction>  $actionClass */
    private static function download(string $actionClass): TableAction|PageAction
    {
        return $actionClass::make('downloadShippingLabel')
            ->label('Étiquette Chronopost (PDF)')
            ->icon('heroicon-o-printer')
            ->visible(fn (Order $record): bool => $record->activeShippingLabel !== null)
            ->action(fn (Order $record): ?StreamedResponse => self::stream($record));
    }

    /** @param  class-string<TableAction>|class-string<PageAction>  $actionClass */
    private static function cancel(string $actionClass): TableAction|PageAction
    {
        return $actionClass::make('cancelShippingLabel')
            ->label('Annuler l\'étiquette')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record): bool => CancelShippingLabelAction::isAvailableFor($record))
            ->requiresConfirmation()
            ->modalDescription('L\'étiquette sera annulée chez Chronopost (ne déposez pas ce colis). Vous pourrez en générer une nouvelle.')
            ->modalSubmitActionLabel('Annuler l\'étiquette')
            ->action(function (Order $record, CancelShippingLabelAction $cancel): void {
                try {
                    $label = $cancel->execute($record);
                } catch (ShippingLabelFailed $e) {
                    Notification::make()->title('Annulation impossible')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title("Étiquette {$label->skybill_number} annulée.")->success()->send();
            });
    }

    private static function stream(Order $record): ?StreamedResponse
    {
        $label = $record->activeShippingLabel()->first(); // relue : l'étiquette vient peut-être d'être créée
        if ($label === null || ! Storage::disk('local')->exists($label->pdf_path)) {
            Notification::make()->title('Fichier de l\'étiquette introuvable.')->danger()->send();

            return null;
        }

        return response()->streamDownload(
            function () use ($label): void {
                echo Storage::disk('local')->get($label->pdf_path);
            },
            "etiquette-chronopost-{$record->number}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
