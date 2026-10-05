<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\SeoSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageSeoSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static string $settings = SeoSettings::class;

    protected static ?string $title = 'SEO global';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Repli par défaut')
                ->description('Utilisé quand une page, catégorie ou produit n\'a pas de meta propres (PLAN.md §17).')
                ->schema([
                    TextInput::make('default_title')->label('Title par défaut')->maxLength(255),
                    Textarea::make('default_description')->label('Description par défaut')->rows(2)->maxLength(300),
                    FileUpload::make('default_og_image_path')->label('Image Open Graph par défaut')->image()->directory('branding'),
                ]),
        ]);
    }
}
