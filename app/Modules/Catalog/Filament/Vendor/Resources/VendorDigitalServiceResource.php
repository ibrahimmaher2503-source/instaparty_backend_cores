<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource\Pages;
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

class VendorDigitalServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-tablet';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.services.digital_services');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.digital_service.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.digital_service.plural');
    }

    public static function getTitle(): string|Htmlable
    {
        return __('catalog.models.digital_service.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorProfile = self::getVendorProfile();

        return parent::getEloquentQuery()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->where('product_type', ProductType::Digital);
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
                                ->whereJsonContains('allowed_product_types', ProductType::Digital->value)
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

            Section::make(__('catalog.digital_details'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('digitalDetail.delivery_method')
                            ->label(__('catalog.delivery_method'))
                            ->options([
                                'email' => 'Email',
                                'sms' => 'SMS',
                                'in_app' => 'In-App',
                                'url' => 'URL',
                            ])
                            ->required(),
                        TextInput::make('digitalDetail.redemption_url_template')
                            ->label(__('catalog.redemption_url_template'))
                            ->url()
                            ->maxLength(500),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('digitalDetail.has_expiry')
                            ->label(__('catalog.has_expiry'))
                            ->default(false)
                            ->live(),
                        Toggle::make('digitalDetail.is_refundable_after_delivery')
                            ->label(__('catalog.is_refundable_after_delivery'))
                            ->default(false),
                    ]),
                    TextInput::make('digitalDetail.expiry_days_after_purchase')
                        ->label(__('catalog.expiry_days_after_purchase'))
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn ($get) => (bool) $get('digitalDetail.has_expiry')),
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
                    ->color(fn (mixed $state): string => $state instanceof ServiceState
                        ? (ServiceStatus::tryFrom($state->getValue())?->color() ?? 'gray')
                        : 'gray')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ServiceState
                        ? (ServiceStatus::tryFrom($state->getValue())?->label() ?? $state->getValue())
                        : (string) $state),
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
            'index' => Pages\ListVendorDigitalServices::route('/'),
            'create' => Pages\CreateVendorDigitalService::route('/create'),
            'edit' => Pages\EditVendorDigitalService::route('/{record}/edit'),
        ];
    }

    protected static function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
