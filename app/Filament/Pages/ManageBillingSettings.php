<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\BillingSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageBillingSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Paramètres';

    protected static string $settings = BillingSettings::class;

    protected static ?string $title = 'Facturation';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Mentions légales vendeur')
                ->description('⚠️ À valider par l\'expert-comptable avant la mise en production (PLAN.md §12).')
                ->schema([
                    TextInput::make('company_name')->label('Raison sociale')->maxLength(255),
                    TextInput::make('legal_form')->label('Forme juridique')->maxLength(255),
                    TextInput::make('share_capital')->label('Capital social')->maxLength(255),
                    TextInput::make('address')->label('Adresse du siège')->maxLength(255),
                    TextInput::make('siret')->label('SIRET')->maxLength(20),
                    TextInput::make('rcs')->label('RCS')->maxLength(255),
                    TextInput::make('vat_number')->label('N° TVA intracommunautaire')->maxLength(50),
                ])
                ->columns(2),

            Section::make('Facture')
                ->schema([
                    Textarea::make('invoice_footer_mentions')->label('Mentions libres (pied de facture)')->rows(3),
                    TextInput::make('invoice_number_format')->label('Format numéro de facture')->placeholder('F{year}-{number}')->maxLength(50),
                    TextInput::make('credit_note_number_format')->label('Format numéro d\'avoir')->placeholder('A{year}-{number}')->maxLength(50),
                ]),
        ]);
    }
}
