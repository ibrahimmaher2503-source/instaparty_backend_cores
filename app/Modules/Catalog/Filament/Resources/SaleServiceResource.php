<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleServiceResource extends Resource
{
    use Translatable;

    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Services';

    protected static ?string $navigationLabel = 'Sale Services';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('product_type', ProductType::Sale);
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('catalog.translatable_fields'))
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label(__('catalog.name')),
                    Textarea::make('short_description')
                        ->required()
                        ->maxLength(1000)
                        ->rows(3)
                        ->label(__('catalog.short_description')),
                    Textarea::make('long_description')
                        ->maxLength(5000)
                        ->rows(5)
                        ->label(__('catalog.long_description')),
                ])
                ->columnSpanFull(),

            Section::make(__('catalog.shared'))
                ->schema([
                    Select::make('category_id')
                        ->relationship('category', 'name->en')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->label(__('catalog.category')),
                    TextInput::make('base_price_minor')
                        ->label(__('catalog.base_price'))
                        ->helperText('In piastres — 10000 = 100.00 EGP')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    Select::make('status')
                        ->options(ServiceStatus::class)
                        ->required()
                        ->label(__('catalog.status')),
                    Toggle::make('is_featured')
                        ->label(__('catalog.is_featured')),
                ])
                ->columns(2),

            Section::make(__('catalog.sale_details'))
                ->relationship('saleDetail')
                ->schema([
                    Toggle::make('is_perishable')
                        ->label(__('catalog.is_perishable')),
                    Toggle::make('is_made_to_order')
                        ->label(__('catalog.is_made_to_order'))
                        ->live(),
                    TextInput::make('lead_time_hours')
                        ->numeric()
                        ->minValue(1)
                        ->label(__('catalog.lead_time_hours'))
                        ->visible(fn ($get): bool => (bool) $get('is_made_to_order')),
                    TextInput::make('stock_quantity')
                        ->numeric()
                        ->minValue(0)
                        ->label(__('catalog.stock_quantity'))
                        ->helperText(__('catalog.stock_quantity_hint')),
                    KeyValue::make('customization_fields')
                        ->reorderable()
                        ->label(__('catalog.customization_fields'))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make(__('catalog.media'))
                ->schema([
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->collection('gallery')
                        ->multiple()
                        ->maxFiles(11)
                        ->image()
                        ->reorderable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->collection('gallery')
                    ->stacked()
                    ->limit(1),
                TextColumn::make('name')
                    ->getStateUsing(fn (Service $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereJsonContains('name->en', $search)->orWhereJsonContains('name->ar', $search))
                    ->limit(40)
                    ->label(__('catalog.name')),
                TextColumn::make('product_type')
                    ->badge()
                    ->color(fn (ProductType $state): string => match ($state) {
                        ProductType::Rental  => 'warning',
                        ProductType::Sale    => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state) => $state->label())
                    ->label(__('catalog.product_type')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ServiceStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ServiceStatus $state): string => $state->label())
                    ->label(__('catalog.status')),
                TextColumn::make('base_price_minor')
                    ->money('EGP', divideBy: 100)
                    ->sortable()
                    ->label(__('catalog.base_price')),
                TextColumn::make('vendor.display_name')
                    ->getStateUsing(fn (Service $record): string => is_array($record->vendor?->display_name)
                        ? ($record->vendor->display_name['en'] ?? '')
                        : (string) ($record->vendor?->display_name ?? ''))
                    ->label(__('catalog.vendor'))
                    ->searchable(false),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ServiceStatus::class),
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleServices::route('/'),
            'create' => Pages\CreateSaleService::route('/create'),
            'edit' => Pages\EditSaleService::route('/{record}/edit'),
        ];
    }
}
