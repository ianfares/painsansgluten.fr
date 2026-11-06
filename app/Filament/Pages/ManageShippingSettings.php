<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\ShippingSettings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
                    Select::make('chronopost_label_mode')
                        ->label('Format des étiquettes')
                        ->options([
                            'THE1015' => 'Imprimante thermique 10×15 (Zebra ZD220)',
                            'PDF' => 'Imprimante classique, feuille A4',
                        ])
                        ->helperText('S\'applique aux prochaines étiquettes générées. En thermique, imprimer le PDF avec le pilote Zebra, échelle 100 %.')
                        ->required()->selectablePlaceholder(false),
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
                    TextInput::make('packaging_weight_g')
                        ->label('Poids de l\'emballage (grammes)')
                        ->helperText('Carton + calage, ajouté une fois par colis : compte dans la tranche de frais de port et sur l\'étiquette Chronopost.')
                        ->numeric()->integer()->minValue(0)->maxValue(5000)->required(),
                ]),

            // Paliers par montant du panier (06/11/2026, offre de lancement) :
            // saisie en euros, stockage en centimes TTC.
            Section::make('Frais de port par montant du panier (Chronopost Relais)')
                ->description('Activé : le prix de la livraison dépend du montant des produits (après remise, hors livraison) et non plus du poids. Désactivé : retour à la grille au poids. Le franco de port, s\'il est activé, reste prioritaire. Le poids maximum d\'un colis reste contrôlé par la grille au poids.')
                ->schema([
                    Toggle::make('amount_tiers_enabled')->label('Frais de port par montant activés')->live(),
                    Repeater::make('amount_tiers')
                        ->label('Paliers')
                        ->helperText('Chaque palier s\'applique à partir de son montant, jusqu\'au palier suivant. Un palier doit commencer à 0 €.')
                        ->schema([
                            TextInput::make('min_subtotal_ttc')
                                ->label('À partir de (€ TTC)')
                                ->numeric()->step(0.01)->minValue(0)->maxValue(10000)->prefix('€')->required()
                                ->formatStateUsing(fn (int|string|null $state): ?float => $state !== null ? round(((int) $state) / 100, 2) : null)
                                ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                            TextInput::make('price_ttc')
                                ->label('Livraison (€ TTC)')
                                ->numeric()->step(0.01)->minValue(0)->maxValue(1000)->prefix('€')->required()
                                ->formatStateUsing(fn (int|string|null $state): ?float => $state !== null ? round(((int) $state) / 100, 2) : null)
                                ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                        ])
                        ->columns(2)
                        ->minItems(1)->maxItems(10)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->addActionLabel('Ajouter un palier')
                        ->rules([
                            fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                $mins = array_map(fn (array $tier): float => (float) ($tier['min_subtotal_ttc'] ?? -1), is_array($value) ? $value : []);
                                if (! in_array(0.0, $mins, true)) {
                                    $fail('Un palier doit commencer à 0 €.');
                                }
                                if (count($mins) !== count(array_unique($mins))) {
                                    $fail('Deux paliers ne peuvent pas commencer au même montant.');
                                }
                            },
                        ]),
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
