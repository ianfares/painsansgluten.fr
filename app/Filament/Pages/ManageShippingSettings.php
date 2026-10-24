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
            Section::make('Date d\'expédition')
                ->description('Les 3 champs sont obligatoires : tant qu\'ils ne sont pas tous remplis, les clients ne peuvent pas commander.')
                ->schema([
                    CheckboxList::make('shipping_weekdays')
                        ->label('Jours d\'expédition')
                        ->options([
                            'monday' => 'Lundi', 'tuesday' => 'Mardi', 'wednesday' => 'Mercredi',
                            'thursday' => 'Jeudi', 'friday' => 'Vendredi', 'saturday' => 'Samedi', 'sunday' => 'Dimanche',
                        ])
                        ->columns(4)
                        ->required(),
                    TimePicker::make('order_cutoff_time')->label('Heure limite de commande')->seconds(false)->required(),
                    TextInput::make('production_lead_days')
                        ->label('Délai de fabrication (jours ouvrés)')
                        ->helperText('Nombre de jours de fabrication avant expédition. 0 = expédié le jour même si la commande arrive avant l\'heure limite.')
                        ->numeric()->minValue(0)->required(),
                ]),

            Section::make('Chronopost')
                ->schema([
                    Toggle::make('chronopost_enabled')
                        ->label('Livraison Chronopost Relais proposée aux clients')
                        ->helperText('Désactivée : le client ne peut choisir qu\'un retrait chez un commerçant partenaire (dans le rayon défini plus bas). Les commandes saisies dans le back-office ne sont pas concernées.'),
                    TextInput::make('chronopost_product_code')->label('Code produit Chronopost')->maxLength(50),
                    TextInput::make('carrier_label')->label('Libellé du transporteur (affiché au client)')->maxLength(100),
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

            // Saisie en euros, stockage en centimes (19/10/2026).
            Section::make('Minimum de commande')
                ->description('Montant minimum des produits (hors frais de livraison, après remise client), pour tous les clients. 0 = pas de minimum.')
                ->schema([
                    TextInput::make('minimum_order_chronopost_ttc')
                        ->label('Minimum en livraison Chronopost Relais (€ TTC)')
                        ->numeric()->step(0.01)->minValue(0)->maxValue(10000)->prefix('€')->required()
                        ->formatStateUsing(fn (?int $state): ?float => $state !== null ? round($state / 100, 2) : null)
                        ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                    TextInput::make('minimum_order_pickup_ttc')
                        ->label('Minimum en retrait (commerçant partenaire ou laboratoire) (€ TTC)')
                        ->numeric()->step(0.01)->minValue(0)->maxValue(10000)->prefix('€')->required()
                        ->formatStateUsing(fn (?int $state): ?float => $state !== null ? round($state / 100, 2) : null)
                        ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
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

            Section::make('Retraits')
                ->schema([
                    TextInput::make('pickup_max_distance_km')
                        ->label('Distance maximale des points de retrait (km, à vol d\'oiseau)')
                        ->helperText('Seuls les commerçants situés à cette distance maximale de l\'adresse du client sont proposés.')
                        ->numeric()->minValue(1)->required(),
                    Textarea::make('lab_pickup_instructions')
                        ->label('Instructions de retrait au laboratoire')
                        ->helperText('Affichées au client pro autorisé au retrait au laboratoire.')
                        ->rows(4),
                ]),

            Section::make('Textes éditables')
                ->schema([
                    Textarea::make('shipping_block_text')->label('Bloc « Expédition et livraison » (fiche produit)')->helperText('Une ligne vide sépare deux paragraphes.')->rows(8),
                    Textarea::make('non_shippable_message')->label('Message produit non expédiable (retrait uniquement)')->rows(2),
                    Textarea::make('relay_pickup_message')->label('Message retrait en relais')->rows(2),
                ]),
        ]);
    }
}
