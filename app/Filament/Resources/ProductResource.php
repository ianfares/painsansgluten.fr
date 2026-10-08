<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Allergen;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Produits';

    /** Les 9 clés du JSON `nutrition` (PLAN.md §6.2), pour 100 g. */
    private const NUTRITION_FIELDS = [
        'kcal' => 'Énergie (kcal)',
        'kj' => 'Énergie (kJ)',
        'fat' => 'Matières grasses (g)',
        'saturated' => 'dont acides gras saturés (g)',
        'carbs' => 'Glucides (g)',
        'sugars' => 'dont sucres (g)',
        'fiber' => 'Fibres (g)',
        'protein' => 'Protéines (g)',
        'salt' => 'Sel (g)',
    ];

    public static function form(Form $form): Form
    {
        $requiredToPublish = fn (Get $get): bool => (bool) $get('is_published');

        return $form->schema([
            Tabs::make('Produit')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Général')
                        ->schema([
                            Select::make('category_id')
                                ->label('Catégorie')
                                ->options(fn () => Category::query()->orderBy('position')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            TextInput::make('name')
                                ->label('Nom')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                    if ($operation === 'create') {
                                        $set('slug', Str::slug((string) $state));
                                    }
                                }),
                            TextInput::make('slug')->label('Slug (URL)')->required()->unique(ignoreRecord: true)->maxLength(255),
                            TextInput::make('reference')->label('Référence')->required()->unique(ignoreRecord: true)->maxLength(255),
                            TextInput::make('price_ttc')
                                ->label('Prix TTC (€)')
                                ->required()
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0)
                                ->prefix('€')
                                ->afterStateHydrated(function (TextInput $component, ?Product $record): void {
                                    $component->state($record ? round($record->price_ttc / 100, 2) : null);
                                })
                                ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                            Select::make('vat_rate')
                                ->label('Taux de TVA')
                                ->options(['2.1' => '2,1 %', '5.5' => '5,5 %', '10' => '10 %', '20' => '20 %'])
                                ->helperText('Taux légaux français — le taux applicable à ce produit reste à valider par le comptable.')
                                ->required(),
                            TextInput::make('position')->label('Position')->numeric()->default(0),
                            Textarea::make('short_description')->label('Description courte')->rows(2)->columnSpanFull(),
                            Grid::make(4)->schema([
                                Toggle::make('is_published')->label('Publié')->live(),
                                Toggle::make('is_available')->label('Disponible')->default(true),
                                Toggle::make('is_shippable')->label('Expédiable')->default(true),
                                Toggle::make('is_featured')->label('Mis en avant'),
                            ]),
                        ])->columns(2),

                    Tab::make('Descriptions')
                        ->schema([
                            RichEditor::make('description')->label('Description')->columnSpanFull(),
                        ]),

                    Tab::make('Composition')
                        ->schema([
                            RichEditor::make('ingredients')
                                ->label('Ingrédients')
                                ->required($requiredToPublish)
                                ->columnSpanFull(),
                            CheckboxList::make('allergens_contains')
                                ->label('Allergènes : contient')
                                ->options(fn () => collect(Allergen::options())->pluck('label', 'value'))
                                ->columns(2),
                            CheckboxList::make('allergens_traces')
                                ->label('Allergènes : traces possibles')
                                ->options(fn () => collect(Allergen::options())->pluck('label', 'value'))
                                ->columns(2),
                            Textarea::make('allergen_note')
                                ->label('Mention atelier')
                                ->placeholder('Fabriqué dans un atelier qui utilise...')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Nutrition')
                        ->schema(
                            collect(self::NUTRITION_FIELDS)
                                ->map(fn (string $label, string $key) => TextInput::make("nutrition.{$key}")->label($label)->numeric()->step('any'))
                                ->values()
                                ->all()
                        )
                        ->columns(3),

                    Tab::make('Conditionnement')
                        ->schema([
                            TextInput::make('net_weight_g')->label('Poids net (g)')->numeric()->required($requiredToPublish),
                            TextInput::make('shipping_weight_g')->label('Poids d\'expédition (g)')->numeric()->required($requiredToPublish),
                            TextInput::make('packaging')->label('Conditionnement')->maxLength(255),
                            TextInput::make('sale_unit')->label('Vendu à l\'unité / par lot')->maxLength(255),
                        ])->columns(2),

                    Tab::make('Conseils')
                        ->schema([
                            Textarea::make('tasting_tips')->label('Conseils de dégustation')->rows(2),
                            Textarea::make('storage')->label('Conservation')->rows(2),
                            TextInput::make('shelf_life')->label('Durée de conservation indicative')->maxLength(255),
                        ]),

                    Tab::make('Images')
                        ->schema([
                            TextInput::make('main_alt')
                                ->label('Texte alternatif (image principale)')
                                ->required($requiredToPublish)
                                ->afterStateHydrated(function (TextInput $component, ?Product $record): void {
                                    $component->state($record?->getFirstMedia('main')?->getCustomProperty('alt'));
                                }),
                            SpatieMediaLibraryFileUpload::make('main')
                                ->label('Image principale')
                                ->collection('main')
                                ->image()
                                ->required($requiredToPublish),
                            SpatieMediaLibraryFileUpload::make('gallery')
                                ->label('Galerie')
                                ->collection('gallery')
                                ->image()
                                ->multiple()
                                ->reorderable()
                                ->appendFiles(),
                            Repeater::make('galleryAltTexts')
                                ->label('Textes alternatifs de la galerie')
                                ->helperText('Disponible après le premier enregistrement des images ci-dessus.')
                                ->schema([
                                    Hidden::make('media_id'),
                                    TextInput::make('name')->label('Fichier')->disabled()->dehydrated(false),
                                    TextInput::make('alt')->label('Texte alternatif')->required(),
                                ])
                                ->afterStateHydrated(function (Repeater $component, ?Product $record): void {
                                    $component->state(
                                        $record
                                            ? $record->getMedia('gallery')
                                                ->map(fn ($media) => [
                                                    'media_id' => $media->id,
                                                    'name' => $media->name,
                                                    'alt' => $media->getCustomProperty('alt'),
                                                ])
                                                ->all()
                                            : []
                                    );
                                })
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columns(3),
                        ]),

                    Tab::make('SEO')
                        ->schema([
                            TextInput::make('seo_title')->label('Title SEO')->maxLength(255),
                            Textarea::make('seo_description')->label('Description SEO')->rows(2)->maxLength(300),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Catégorie')->sortable(),
                TextColumn::make('price_ttc')->label('Prix TTC')->money('EUR', divideBy: 100)->sortable(),
                IconColumn::make('is_published')->label('Publié')->boolean(),
                IconColumn::make('is_available')->label('Disponible')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Catégorie')->relationship('category', 'name'),
                TernaryFilter::make('is_available')->label('Disponible'),
                TernaryFilter::make('is_published')->label('Publié'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggleAvailability')
                    ->label(fn (Product $record): string => $record->is_available ? 'Marquer indisponible' : 'Marquer disponible')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (Product $record) => $record->update(['is_available' => ! $record->is_available])),
                Tables\Actions\Action::make('duplicate')
                    ->label('Dupliquer')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Product $record): void {
                        $copy = $record->replicate(['slug', 'reference']);
                        $copy->name = $record->name.' (copie)';
                        $copy->slug = Str::slug($record->slug.'-copie-'.Str::random(5));
                        $copy->reference = $record->reference.'-COPIE-'.Str::upper(Str::random(5));
                        $copy->is_available = false;
                        $copy->is_published = false;
                        $copy->save();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Product $record, Tables\Actions\DeleteAction $action): void {
                        if ($record->hasBeenOrdered()) {
                            Notification::make()
                                ->title('Suppression impossible')
                                ->body('Ce produit figure dans au moins une commande. Désactivez-le au lieu de le supprimer.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $blocked = $records->filter(
                                fn (Model $record): bool => $record instanceof Product && $record->hasBeenOrdered()
                            );

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Suppression partielle')
                                    ->body($blocked->count().' produit(s) déjà commandé(s) n\'ont pas été supprimés.')
                                    ->warning()
                                    ->send();
                            }

                            $records->diff($blocked)->each(function (Model $record): void {
                                if ($record instanceof Product) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
