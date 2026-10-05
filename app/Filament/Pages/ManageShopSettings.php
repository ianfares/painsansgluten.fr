<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\ShopSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageShopSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static string $settings = ShopSettings::class;

    protected static ?string $title = 'Boutique';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Coordonnées')
                ->description('Affichées en pied de page et dans les données structurées (schema.org).')
                ->schema([
                    TextInput::make('shop_name')->label('Nom de la boutique')->maxLength(255),
                    TextInput::make('contact_email')->label('Email de contact')->email()->maxLength(255),
                    TextInput::make('contact_phone')->label('Téléphone')->tel()->maxLength(30),
                    TextInput::make('address_line1')->label('Adresse')->maxLength(255),
                    TextInput::make('postal_code')->label('Code postal')->maxLength(10),
                    TextInput::make('city')->label('Ville')->maxLength(255),
                    TextInput::make('facebook_url')->label('Lien Facebook')->url()->maxLength(255),
                ])
                ->columns(2),

            Section::make('Emails')
                ->schema([
                    TextInput::make('sender_email')->label('Email expéditeur')->email()->maxLength(255),
                    TextInput::make('reply_to_email')->label('Email de réponse')->email()->maxLength(255),
                    TextInput::make('admin_notification_email')->label('Email de notification admin')->email()->maxLength(255),
                ])
                ->columns(3),
        ]);
    }
}
