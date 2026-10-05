<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\ShippingSettings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageShippingSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static string $settings = ShippingSettings::class;

    protected static ?string $title = 'Expédition';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Date d\'expédition (PLAN.md §8.4)')
                ->description('Tant que ces paramètres sont incomplets, le tunnel de commande reste bloqué (T09/T13).')
                ->schema([
                    CheckboxList::make('shipping_weekdays')
                        ->label('Jours d\'expédition')
                        ->options([
                            'monday' => 'Lundi', 'tuesday' => 'Mardi', 'wednesday' => 'Mercredi',
                            'thursday' => 'Jeudi', 'friday' => 'Vendredi', 'saturday' => 'Samedi', 'sunday' => 'Dimanche',
                        ])
                        ->columns(4),
                    TimePicker::make('order_cutoff_time')->label('Heure limite de commande')->seconds(false),
                    TextInput::make('production_lead_days')->label('Délai de fabrication (jours ouvrés)')->numeric()->minValue(0),
                ]),

            Section::make('Chronopost')
                ->schema([
                    TextInput::make('chronopost_product_code')->label('Code produit Chronopost')->maxLength(50),
                    TextInput::make('tracking_url_template')
                        ->label('Modèle d\'URL de suivi')
                        ->placeholder('https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking}')
                        ->helperText('Le jeton {tracking} est remplacé par le numéro de suivi.')
                        ->maxLength(500),
                ]),

            Section::make('Panier')
                ->schema([
                    TextInput::make('max_quantity_per_line')->label('Quantité maximum par ligne')->numeric()->minValue(1)->required(),
                ]),

            Section::make('Frais de port')
                ->schema([
                    Toggle::make('free_shipping_enabled')->label('Franco de port activé')->live(),
                    TextInput::make('free_shipping_threshold_ttc')
                        ->label('Seuil de franco (centimes TTC)')
                        ->numeric()->minValue(0)
                        ->visible(fn (callable $get) => $get('free_shipping_enabled')),
                    TextInput::make('shipping_vat_rate')->label('Taux de TVA sur les frais de port (%)')->numeric()->step(0.01),
                ]),

            Section::make('Textes éditables')
                ->schema([
                    Textarea::make('shipping_block_text')->label('Bloc expédition (fiche produit)')->rows(2),
                    Textarea::make('non_shippable_message')->label('Message produit non expédiable')->rows(2),
                    Textarea::make('relay_pickup_message')->label('Message retrait en relais')->rows(2),
                ]),
        ]);
    }
}
