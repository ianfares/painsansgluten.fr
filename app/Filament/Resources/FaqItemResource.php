<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FaqItemResource\Pages;
use App\Models\FaqItem;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * FAQ (PLAN.md §16.2) : question, réponse (RichEditor purifié), groupe,
 * position, publiée.
 */
class FaqItemResource extends Resource
{
    protected static ?string $model = FaqItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Contenus';

    protected static ?string $navigationLabel = 'FAQ';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('question')->label('Question')->required()->maxLength(255)->columnSpanFull(),
            RichEditor::make('answer')->label('Réponse')->required()->columnSpanFull(),
            TextInput::make('group')->label('Groupe')->maxLength(255),
            TextInput::make('position')->label('Position')->numeric()->default(0),
            Toggle::make('is_published')->label('Publiée'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->label('Question')->searchable()->limit(60),
                TextColumn::make('group')->label('Groupe'),
                TextColumn::make('position')->label('Position')->sortable(),
                IconColumn::make('is_published')->label('Publiée')->boolean(),
            ])
            ->defaultSort('position')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqItems::route('/'),
            'create' => Pages\CreateFaqItem::route('/create'),
            'edit' => Pages\EditFaqItem::route('/{record}/edit'),
        ];
    }
}
