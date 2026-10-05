<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        $blockIfOrdered = function (Product $record, Actions\Action $action): void {
            if ($record->hasBeenOrdered()) {
                Notification::make()
                    ->title('Suppression impossible')
                    ->body('Ce produit figure dans au moins une commande. Désactivez-le au lieu de le supprimer.')
                    ->danger()
                    ->send();
                $action->cancel();
            }
        };

        return [
            Actions\DeleteAction::make()->before($blockIfOrdered),
            Actions\ForceDeleteAction::make()->before($blockIfOrdered),
            Actions\RestoreAction::make(),
        ];
    }

    /**
     * Persiste les textes alternatifs de la galerie (PLAN.md §6.2 : "texte
     * alternatif obligatoire par image"), saisis via le Repeater
     * `galleryAltTexts` — le plugin Filament media-library ne propose pas
     * de champ par-fichier nativement (voir docs/DECISIONS.md, T05).
     */
    protected function afterSave(): void
    {
        foreach ($this->data['galleryAltTexts'] ?? [] as $item) {
            if (! empty($item['media_id'])) {
                Media::query()->find($item['media_id'])?->setCustomProperty('alt', $item['alt'] ?? null)->save();
            }
        }

        $mainAlt = $this->data['main_alt'] ?? null;
        if ($mainAlt !== null && $this->record instanceof Product) {
            $this->record->getFirstMedia('main')?->setCustomProperty('alt', $mainAlt)->save();
        }
    }
}
