<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\FreeGiftSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

/** Pâtisserie offerte au-delà d'un montant d'achat (20/10/2026). */
class ManageFreeGiftSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static string $settings = FreeGiftSettings::class;

    protected static ?string $title = 'Pâtisserie offerte';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Pâtisserie offerte')
                ->description('Au-delà du montant indiqué, une ligne « offerte » à 0 € est ajoutée à la commande (tous les clients, particuliers et professionnels, y compris les commandes saisies dans le back-office). Montant calculé sur les produits, après remise client et hors frais de livraison.')
                ->schema([
                    Toggle::make('enabled')->label('Activer la pâtisserie offerte')->live(),
                    // Saisie en euros, stockage en centimes.
                    TextInput::make('threshold_ttc')
                        ->label('Montant d\'achat à atteindre (€ TTC)')
                        ->numeric()->step(0.01)->maxValue(10000)->prefix('€')->required()
                        ->minValue(fn (callable $get): float => $get('enabled') ? 0.01 : 0)
                        ->formatStateUsing(fn (?int $state): ?float => $state !== null ? round($state / 100, 2) : null)
                        ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                    TextInput::make('label')
                        ->label('Libellé de la ligne')
                        ->helperText('Affiché dans le panier, la commande, la facture et le bon de livraison.')
                        ->required()->maxLength(100),
                    TextInput::make('weight_g')
                        ->label('Poids ajouté au colis (grammes)')
                        ->helperText('Compté dans le calcul des frais de port Chronopost.')
                        ->numeric()->integer()->maxValue(5000)->suffix('g')->required()
                        ->minValue(fn (callable $get): int => $get('enabled') ? 1 : 0),
                ]),
        ]);
    }
}
