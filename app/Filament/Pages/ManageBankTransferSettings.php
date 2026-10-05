<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Rules\Iban;
use App\Settings\BankTransferSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageBankTransferSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static string $settings = BankTransferSettings::class;

    protected static ?string $title = 'Virement bancaire';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Coordonnées bancaires')
                ->description('Communiquées au client dans l\'email d\'instructions de virement (PLAN.md §11).')
                ->schema([
                    TextInput::make('account_holder')->label('Titulaire du compte')->maxLength(255),
                    TextInput::make('iban')->label('IBAN')->rule(new Iban)->maxLength(34),
                    TextInput::make('bic')->label('BIC')->maxLength(11),
                    TextInput::make('bank_name')->label('Nom de la banque')->maxLength(255),
                ])
                ->columns(2),

            Section::make('Annulation automatique')
                ->schema([
                    TextInput::make('cancel_after_days')
                        ->label('Délai avant annulation (jours)')
                        ->numeric()->minValue(1)->maxValue(60)->required(),
                    Toggle::make('auto_cancel_enabled')->label('Annulation automatique activée'),
                ]),
        ]);
    }
}
