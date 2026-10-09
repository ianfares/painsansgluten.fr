<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\ShopSettings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
                    TextInput::make('instagram_url')->label('Lien Instagram')->url()->maxLength(255),
                    TextInput::make('google_business_url')
                        ->label('Lien de la fiche Google Business')
                        ->helperText('Aide Google et les IA à relier le site à la boutique.')
                        ->url()->maxLength(500),
                ])
                ->columns(2),

            Section::make('Horaires d\'ouverture')
                ->description('Affichés dans les résultats Google et repris par les assistants IA. Un créneau par ligne (ex. lundi 8:00 – 12:30, puis lundi 14:00 – 19:00).')
                ->schema([
                    Repeater::make('opening_hours')
                        ->label('')
                        ->schema([
                            Select::make('day')->label('Jour')->required()->options([
                                'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi', 'Thursday' => 'Jeudi',
                                'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche',
                            ]),
                            TimePicker::make('opens')->label('Ouverture')->seconds(false)->required(),
                            TimePicker::make('closes')->label('Fermeture')->seconds(false)->required()->after('opens'),
                        ])
                        ->columns(3)
                        ->addActionLabel('Ajouter un créneau')
                        ->defaultItems(0),
                ]),

            Section::make('Espace professionnels')
                ->description('Page publique « Professionnels » (/professionnels) : texte de présentation et choix du type d\'activité du formulaire de demande de compte pro.')
                ->schema([
                    RichEditor::make('pro_intro_html')->label('Texte de présentation')->columnSpanFull(),
                    TagsInput::make('pro_activity_types')
                        ->label('Types d\'activité proposés')
                        ->helperText('Un type par entrée. « Autre » fait apparaître la précision demandée au visiteur : gardez-le dans la liste.')
                        ->columnSpanFull(),
                ]),

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
