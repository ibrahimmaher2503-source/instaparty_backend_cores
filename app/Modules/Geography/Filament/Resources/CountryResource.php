<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources;

use App\Modules\Geography\Domain\Models\Country;
use App\Modules\Geography\Filament\Resources\CountryResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.geography');
    }

    public static function getNavigationLabel(): string
    {
        return __('geography.countries');
    }

    public static function getModelLabel(): string
    {
        return __('geography.country');
    }

    public static function getPluralModelLabel(): string
    {
        return __('geography.countries');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('geography.country'))
                ->schema([
                    TextInput::make('iso2')
                        ->label(__('geography.columns.iso2'))
                        ->required()
                        ->maxLength(2)
                        ->unique(ignoreRecord: true),
                    TextInput::make('iso3')
                        ->label(__('geography.columns.iso3'))
                        ->required()
                        ->maxLength(3)
                        ->unique(ignoreRecord: true),
                    TextInput::make('default_currency')
                        ->label(__('geography.columns.default_currency'))
                        ->required()
                        ->maxLength(3),
                    TextInput::make('default_locale')
                        ->label(__('geography.columns.default_locale'))
                        ->required()
                        ->maxLength(10),
                    TextInput::make('default_timezone')
                        ->label(__('geography.columns.default_timezone'))
                        ->required()
                        ->maxLength(80),
                    TextInput::make('phone_code')
                        ->label(__('geography.columns.phone_code'))
                        ->required()
                        ->maxLength(10),
                    TextInput::make('sort_order')
                        ->label(__('geography.columns.sort_order'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Toggle::make('is_active')
                        ->label(__('geography.columns.is_active'))
                        ->default(true),
                ])
                ->columns(4),
            Tabs::make(__('geography.columns.name'))
                ->tabs([
                    Tabs\Tab::make('English')->schema([
                        TextInput::make('name.en')
                            ->label(__('geography.columns.name_en'))
                            ->required()
                            ->maxLength(255)
                            ->dir('ltr'),
                    ]),
                    Tabs\Tab::make('Arabic')->schema([
                        TextInput::make('name.ar')
                            ->label(__('geography.columns.name_ar'))
                            ->required()
                            ->maxLength(255)
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
                TextColumn::make('iso2')
                    ->label(__('geography.columns.iso2'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('geography.columns.name'))
                    ->getStateUsing(fn (Country $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('default_currency')
                    ->label(__('geography.columns.default_currency'))
                    ->badge(),
                TextColumn::make('default_locale')
                    ->label(__('geography.columns.default_locale')),
                TextColumn::make('sort_order')
                    ->label(__('geography.columns.sort_order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('geography.columns.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('geography.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('geography.filters.is_active')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCountries::route('/'),
            'create' => Pages\CreateCountry::route('/create'),
            'edit' => Pages\EditCountry::route('/{record}/edit'),
        ];
    }
}
