<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\CreateOccasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\EditOccasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource\Pages\ListOccasions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class OccasionResource extends Resource
{
    use Translatable;

    protected static ?string $model = Occasion::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.catalog');
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.occasions');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.occasion.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.occasion.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('catalog.occasion_details'))
                ->schema([
                    TextInput::make('code')
                        ->required()
                        ->maxLength(60)
                        ->unique(ignoreRecord: true)
                        ->label(__('catalog.code')),

                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label(__('catalog.sort_order')),

                    Toggle::make('is_active')
                        ->default(true)
                        ->label(__('catalog.is_active')),
                ])
                ->columns(3),

            Tabs::make('Translations')
                ->tabs([
                    Tabs\Tab::make('English')
                        ->schema([
                            TextInput::make('name.en')
                                ->required()
                                ->maxLength(255)
                                ->label('Name (English)'),
                            TextInput::make('description.en')
                                ->maxLength(500)
                                ->label('Description (English)'),
                        ]),
                    Tabs\Tab::make('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')
                        ->schema([
                            TextInput::make('name.ar')
                                ->required()
                                ->maxLength(255)
                                ->label('Ø§Ù„Ø§Ø³Ù… (Ø¹Ø±Ø¨ÙŠ)')
                                ->extraInputAttributes(['dir' => 'rtl']),
                            TextInput::make('description.ar')
                                ->maxLength(500)
                                ->label('Ø§Ù„ÙˆØµÙ (Ø¹Ø±Ø¨ÙŠ)')
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->label(__('catalog.code')),

                TextColumn::make('name')
                    ->getStateUsing(fn (Occasion $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable(query: fn ($query, $search) => $query->whereJsonContains('name->en', $search)->orWhereJsonContains('name->ar', $search))
                    ->label(__('catalog.name')),

                ToggleColumn::make('is_active')
                    ->label(__('catalog.is_active')),

                TextColumn::make('sort_order')
                    ->sortable()
                    ->label(__('catalog.sort_order')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOccasions::route('/'),
            'create' => CreateOccasion::route('/create'),
            'edit' => EditOccasion::route('/{record}/edit'),
        ];
    }
}
