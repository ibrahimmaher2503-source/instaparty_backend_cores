<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources;

use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Filament\Resources\GovernorateResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GovernorateResource extends Resource
{
    use Translatable;

    protected static ?string $model = Governorate::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.geography');
    }

    public static function getNavigationLabel(): string
    {
        return __('geography.governorates');
    }

    public static function getModelLabel(): string
    {
        return __('geography.governorate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('geography.governorates');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('geography.governorate'))
                ->schema([
                    Select::make('country_id')
                        ->label(__('geography.country'))
                        ->relationship('country', 'name->en')
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('name')
                        ->label(__('geography.columns.name'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('code')
                        ->label(__('geography.columns.code'))
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true),

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
                TextColumn::make('code')
                    ->label(__('geography.columns.code'))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('geography.columns.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('country.name')
                    ->label(__('geography.columns.country'))
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
                SelectFilter::make('country_id')
                    ->label(__('geography.filters.country'))
                    ->relationship('country', 'name->en')
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
            'index' => Pages\ListGovernorates::route('/'),
            'create' => Pages\CreateGovernorate::route('/create'),
            'edit' => Pages\EditGovernorate::route('/{record}/edit'),
        ];
    }
}
