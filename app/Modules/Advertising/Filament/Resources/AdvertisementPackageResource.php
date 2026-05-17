<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources;

use App\Modules\Advertising\Domain\Enums\PlacementType;
use App\Modules\Advertising\Domain\Models\AdvertisementPackage;
use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdvertisementPackageResource extends Resource
{
    use Translatable;

    protected static ?string $model = AdvertisementPackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.advertising');
    }

    public static function getModelLabel(): string
    {
        return __('advertising.package');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising.packages');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Translations')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('English')
                        ->schema([
                            Forms\Components\TextInput::make('name.en')
                                ->label('Name (EN)')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description.en')
                                ->label('Description (EN)')
                                ->rows(3),
                        ]),
                    Forms\Components\Tabs\Tab::make('العربية')
                        ->schema([
                            Forms\Components\TextInput::make('name.ar')
                                ->label('Name (AR)')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description.ar')
                                ->label('Description (AR)')
                                ->rows(3),
                        ]),
                ])
                ->columnSpanFull(),

            Forms\Components\Section::make(__('advertising.package_details'))
                ->schema([
                    Forms\Components\Select::make('placement_type')
                        ->label(__('advertising.placement_type'))
                        ->options(collect(PlacementType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->required(),

                    Forms\Components\TextInput::make('duration_days')
                        ->label(__('advertising.duration_days'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->suffix(__('advertising.days')),

                    Forms\Components\TextInput::make('price_minor')
                        ->label(__('advertising.price'))
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->suffix('EGP (piastres)'),

                    Forms\Components\TextInput::make('impression_limit')
                        ->label(__('advertising.impression_limit'))
                        ->numeric()
                        ->minValue(1)
                        ->placeholder(__('advertising.unlimited')),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('advertising.is_active'))
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('advertising.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('placement_type')
                    ->label(__('advertising.placement_type'))
                    ->badge()
                    ->color(fn (PlacementType $state): string => $state->color())
                    ->formatStateUsing(fn (PlacementType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('duration_days')
                    ->label(__('advertising.duration_days'))
                    ->suffix(' days')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_minor')
                    ->label(__('advertising.price'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('impression_limit')
                    ->label(__('advertising.impression_limit'))
                    ->placeholder('∞'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('advertising.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('placement_type')
                    ->options(collect(PlacementType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->label(__('advertising.placement_type')),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('advertising.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdvertisementPackages::route('/'),
            'create' => Pages\CreateAdvertisementPackage::route('/create'),
            'edit'   => Pages\EditAdvertisementPackage::route('/{record}/edit'),
        ];
    }
}
