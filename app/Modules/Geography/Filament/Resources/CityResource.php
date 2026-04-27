<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Geography\Filament\Resources\CityResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Concerns\Translatable;
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
    use Translatable;

    protected static ?string $model = City::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Geography';

    protected static ?int $navigationSort = 4;

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('geography.city'))
                ->schema([
                    Select::make('region_id')
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
                        ->relationship('governorate', 'name->en')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled()
                        ->dehydrated(),

                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('latitude')
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90),

                    TextInput::make('longitude')
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180),

                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

                    Toggle::make('is_active')
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
                    ->searchable()
                    ->sortable(),

                TextColumn::make('region.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('governorate.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('latitude')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sort_order')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('governorate_id')
                    ->relationship('governorate', 'name->en')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('region_id')
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
