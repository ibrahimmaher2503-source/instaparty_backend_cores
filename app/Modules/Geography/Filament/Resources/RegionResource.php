<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources;

use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Geography\Filament\Resources\RegionResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.geography');
    }

    public static function getNavigationLabel(): string
    {
        return __('geography.regions');
    }

    public static function getModelLabel(): string
    {
        return __('geography.region');
    }

    public static function getPluralModelLabel(): string
    {
        return __('geography.regions');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('geography.region'))
                ->schema([
                    Select::make('governorate_id')
                        ->label(__('geography.governorate'))
                        ->relationship('governorate', 'name->en')
                        ->searchable()
                        ->preload()
                        ->required(),

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
                    ->getStateUsing(fn (Region $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('governorate.name')
                    ->label(__('geography.columns.governorate'))
                    ->searchable()
                    ->sortable(),

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
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Region $record): void {
                        if ($record->cities()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title(__('geography.errors.delete_region_has_cities'))
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }
}
