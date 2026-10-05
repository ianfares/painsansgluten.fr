<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Texte alternatif de l'image principale (voir EditProduct::afterSave) :
     * à la création, la galerie est encore vide, seule l'image principale
     * peut déjà avoir un texte alternatif.
     */
    protected function afterCreate(): void
    {
        $mainAlt = $this->data['main_alt'] ?? null;

        if ($mainAlt !== null && $this->record instanceof Product) {
            $this->record->getFirstMedia('main')?->setCustomProperty('alt', $mainAlt)->save();
        }
    }
}
