<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Pages de contenu (PLAN.md §16.1). Contenu riche purifié à l'écriture
 * (modèle `Page`). CLAUDE.md §3.1 : aucun texte juridique inventé —
 * laissé en placeholder tant que la cliente ne l'a pas fourni.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contenus';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')->label('Slug (URL)')->required()->unique(ignoreRecord: true)->maxLength(255),
                Toggle::make('is_published')->label('Publiée'),
            ])->columns(3),

            RichEditor::make('content')->label('Contenu')->helperText('Avec des onglets, ce texte s\'affiche au-dessus d\'eux.')->columnSpanFull(),

            // Onglets (24/10/2026, « Où nous trouver ») : points de retrait, marchés, revendeurs…
            Repeater::make('tabs')
                ->label('Onglets')
                ->helperText('Facultatif. Chaque onglet a son titre et son texte ; glissez-les pour changer l\'ordre.')
                ->schema([
                    TextInput::make('title')->label('Titre de l\'onglet')->required()->maxLength(60),
                    RichEditor::make('content')->label('Texte de l\'onglet'),
                    Toggle::make('show_pickup_points')
                        ->label('Afficher automatiquement la liste des points de retrait')
                        ->helperText('Sous le texte : points de retrait actifs, avec adresse, jour et plage de retrait (rubrique Points de retrait).'),
                ])
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->addActionLabel('Ajouter un onglet')
                ->collapsible()
                ->reorderableWithButtons()
                ->defaultItems(0)
                ->columnSpanFull(),

            Section::make('SEO')->schema([
                TextInput::make('seo_title')->label('Title SEO')->maxLength(255),
                TextInput::make('seo_description')->label('Description SEO')->maxLength(300),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titre')->searchable(),
                TextColumn::make('slug')->label('Slug'),
                IconColumn::make('is_published')->label('Publiée')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
