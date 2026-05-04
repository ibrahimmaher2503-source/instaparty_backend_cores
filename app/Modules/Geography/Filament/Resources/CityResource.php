<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Geography\Filament\Resources\CityResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.geography');
    }

    public static function getNavigationLabel(): string
    {
        return __('geography.cities');
    }

    public static function getModelLabel(): string
    {
        return __('geography.city');
    }

    public static function getPluralModelLabel(): string
    {
        return __('geography.cities');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('geography.city'))
                ->schema([
                    Select::make('region_id')
                        ->label(__('geography.region'))
                        ->relationship('region', 'name->en')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === null) {
                                return;
                            }
                            $region = Region::find($state);
                            if ($region !== null) {
                                $set('governorate_id', $region->governorate_id);
                            }
                        }),

                    Select::make('governorate_id')
                        ->label(__('geography.governorate'))
                        ->relationship('governorate', 'name->en')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled()
                        ->dehydrated(),

                    Tabs::make(__('geography.columns.name'))
                        ->tabs([
                            Tabs\Tab::make('English')
                                ->schema([
                                    TextInput::make('name.en')
                                        ->label(__('geography.columns.name_en'))
                                        ->required()
                                        ->maxLength(255)
                                        ->dir('ltr'),
                                ]),
                            Tabs\Tab::make('Arabic')
                                ->schema([
                                    TextInput::make('name.ar')
                                        ->label(__('geography.columns.name_ar'))
                                        ->required()
                                        ->maxLength(255)
                                        ->extraInputAttributes(['dir' => 'rtl']),
                                ]),
                        ])
                        ->columnSpanFull(),

                    TextInput::make('latitude')
                        ->label(__('geography.columns.latitude'))
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90),

                    TextInput::make('longitude')
                        ->label(__('geography.columns.longitude'))
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180),

                    TextInput::make('sort_order')
                        ->label(__('geography.columns.sort_order'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

                    Toggle::make('is_active')
                        ->label(__('geography.columns.is_active'))
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('geography.columns.name'))
                    ->getStateUsing(fn (City $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('region.name')
                    ->label(__('geography.columns.region'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('governorate.name')
                    ->label(__('geography.columns.governorate'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('latitude')
                    ->label(__('geography.columns.latitude'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude')
                    ->label(__('geography.columns.longitude'))
                    ->toggleable(isToggledHiddenByDefault: true),

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
                SelectFilter::make('governorate_id')
                    ->label(__('geography.filters.governorate'))
                    ->relationship('governorate', 'name->en')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('region_id')
                    ->label(__('geography.filters.region'))
                    ->relationship('region', 'name->en')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
