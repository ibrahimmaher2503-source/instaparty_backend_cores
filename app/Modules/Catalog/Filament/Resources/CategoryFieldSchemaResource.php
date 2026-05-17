<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Application\Actions\DeleteCategoryFieldSchemaAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryFieldSchemaResource extends Resource
{
    use Translatable;

    protected static ?string $model = CategoryFieldSchema::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.catalog');
    }

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.category_field_schemas');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.category_field_schema.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.category_field_schema.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('catalog.category_field_schema_details'))
                ->schema([
                    Select::make('category_id')
                        ->label(__('catalog.category'))
                        ->relationship('category', 'code')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('product_type')
                        ->label(__('catalog.product_type'))
                        ->options(collect(ProductType::cases())->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->label()]))
                        ->required(),
                    TextInput::make('field_key')
                        ->label(__('catalog.field_key'))
                        ->required()
                        ->maxLength(80),
                    Select::make('field_type')
                        ->label(__('catalog.field_type'))
                        ->options([
                            'text' => __('catalog.field_types.text'),
                            'number' => __('catalog.field_types.number'),
                            'boolean' => __('catalog.field_types.boolean'),
                            'select' => __('catalog.field_types.select'),
                            'multiselect' => __('catalog.field_types.multiselect'),
                            'date' => __('catalog.field_types.date'),
                        ])
                        ->required(),
                    TextInput::make('sort_order')
                        ->label(__('catalog.sort_order'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Toggle::make('is_required')
                        ->label(__('catalog.is_required')),
                    Toggle::make('is_filterable')
                        ->label(__('catalog.is_filterable')),
                ])
                ->columns(4),
            Tabs::make(__('catalog.field_label'))
                ->tabs([
                    Tabs\Tab::make('English')->schema([
                        TextInput::make('field_label.en')
                            ->label(__('catalog.field_label_en'))
                            ->required()
                            ->maxLength(255),
                    ]),
                    Tabs\Tab::make('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')->schema([
                        TextInput::make('field_label.ar')
                            ->label(__('catalog.field_label_ar'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                ]),
            Section::make(__('catalog.advanced_schema'))
                ->schema([
                    KeyValue::make('options')
                        ->label(__('catalog.options')),
                    KeyValue::make('validation_rules')
                        ->label(__('catalog.validation_rules')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.code')
                    ->label(__('catalog.category'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('field_key')
                    ->label(__('catalog.field_key'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('field_label')
                    ->label(__('catalog.field_label'))
                    ->getStateUsing(fn (CategoryFieldSchema $record): string => $record->getTranslation('field_label', app()->getLocale(), useFallbackLocale: true)),
                Tables\Columns\TextColumn::make('field_type')
                    ->label(__('catalog.field_type'))
                    ->badge(),
                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('catalog.is_required'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_filterable')
                    ->label(__('catalog.is_filterable'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('catalog.sort_order'))
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->using(function (CategoryFieldSchema $record): bool {
                        app(DeleteCategoryFieldSchemaAction::class)->execute($record, auth()->user());

                        return true;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoryFieldSchemas::route('/'),
            'create' => Pages\CreateCategoryFieldSchema::route('/create'),
            'edit' => Pages\EditCategoryFieldSchema::route('/{record}/edit'),
        ];
    }
}
