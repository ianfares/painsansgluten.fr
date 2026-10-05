<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Product;
use App\Settings\HomepageSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageHomepageSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static string $settings = HomepageSettings::class;

    protected static ?string $title = 'Accueil & apparence';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identité visuelle')
                ->description('Couleurs et polices restent figées dans le code en V1 (PLAN.md §16.3).')
                ->schema([
                    FileUpload::make('logo_path')->label('Logo')->image()->directory('branding'),
                    FileUpload::make('favicon_path')->label('Favicon')->image()->directory('branding'),
                ])
                ->columns(2),

            Section::make('Bandeau d\'annonce')
                ->schema([
                    Toggle::make('announcement_active')->label('Actif')->live(),
                    TextInput::make('announcement_text')->label('Texte')->maxLength(255)
                        ->visible(fn (callable $get) => $get('announcement_active')),
                ]),

            Section::make('Bannière d\'accueil')
                ->schema([
                    FileUpload::make('banner_image_path')->label('Image')->image()->directory('branding'),
                    TextInput::make('banner_title')->label('Titre')->maxLength(255),
                    TextInput::make('banner_subtitle')->label('Sous-titre')->maxLength(255),
                    TextInput::make('banner_button_text')->label('Texte du bouton')->maxLength(100),
                    TextInput::make('banner_button_url')->label('Lien du bouton')->url()->maxLength(255),
                ])
                ->columns(2),

            Section::make('Contenu')
                ->schema([
                    Textarea::make('presentation_text')->label('Texte de présentation')->rows(4),
                    Select::make('featured_product_ids')
                        ->label('Produits mis en avant')
                        ->multiple()
                        ->options(fn () => Product::query()->pluck('name', 'id'))
                        ->searchable(),
                ]),
        ]);
    }
}
