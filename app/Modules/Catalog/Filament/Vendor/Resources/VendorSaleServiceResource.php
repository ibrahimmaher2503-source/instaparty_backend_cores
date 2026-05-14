<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class VendorSaleServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.services.sale_services');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.sale_service.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.sale_service.plural');
    }

    public static function getTitle(): string|Htmlable
    {
        return __('catalog.models.sale_service.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorProfile = self::getVendorProfile();

        return parent::getEloquentQuery()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->where('product_type', ProductType::Sale);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Translations')
                ->tabs([
                    Tabs\Tab::make('English')
                        ->schema([
                            TextInput::make('name.en')
                                ->label(__('catalog.name_en'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('short_description.en')
                                ->label(__('catalog.short_description').' (EN)')
                                ->rows(3)
                                ->maxLength(500),
                            Textarea::make('long_description.en')
                                ->label(__('catalog.long_description').' (EN)')
                                ->rows(5),
                        ]),
                    Tabs\Tab::make('العربية')
                        ->schema([
                            TextInput::make('name.ar')
                                ->label(__('catalog.name_ar'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('short_description.ar')
                                ->label(__('catalog.short_description').' (AR)')
                                ->rows(3)
                                ->maxLength(500),
                            Textarea::make('long_description.ar')
                                ->label(__('catalog.long_description').' (AR)')
                                ->rows(5),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('catalog.shared'))
                ->schema([
                    Select::make('category_id')
                        ->label(__('catalog.category'))
                        ->options(function () {
                            return Category::query()
                                ->where('is_active', true)
                                ->whereJsonContains('allowed_product_types', ProductType::Sale->value)
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn (Category $c) => [
                                    $c->id => $c->getTranslation('name', app()->getLocale()),
                                ]);
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('base_price_minor')
                        ->label(__('catalog.base_price'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('Piastres'),
                ])
                ->columns(2),

            Section::make(__('catalog.sale_details'))
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('saleDetail.is_perishable')
                            ->label(__('catalog.is_perishable'))
                            ->default(false),
                        Toggle::make('saleDetail.is_made_to_order')
                            ->label(__('catalog.is_made_to_order'))
                            ->default(false),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('saleDetail.lead_time_hours')
                            ->label(__('catalog.lead_time_hours'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('saleDetail.stock_quantity')
                            ->label(__('catalog.stock_quantity'))
                            ->numeric()
                            ->minValue(0)
                            ->hint(__('catalog.stock_quantity_hint')),
                    ]),
                ]),

            Section::make(__('catalog.media'))
                ->schema([
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->collection('gallery')
                        ->multiple()
                        ->maxFiles(11)
                        ->image()
                        ->reorderable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->collection('gallery')
                    ->circular()
                    ->stacked()
                    ->limit(1),
                TextColumn::make('name')
                    ->label(__('catalog.name'))
                    ->formatStateUsing(fn (Service $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('catalog.status_label'))
                    ->badge()
                    ->color(fn (ServiceStatus $state) => $state->color())
                    ->formatStateUsing(fn (ServiceStatus $state) => $state->label()),
                TextColumn::make('base_price_minor')
                    ->label(__('catalog.base_price'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('updated_at')
                    ->label(__('vendor-portal.services.last_modified'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorSaleServices::route('/'),
            'create' => Pages\CreateVendorSaleService::route('/create'),
            'edit' => Pages\EditVendorSaleService::route('/{record}/edit'),
        ];
    }

    protected static function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
