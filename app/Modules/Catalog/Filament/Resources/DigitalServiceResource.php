<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Application\Actions\ImportDigitalServicesFromExcelAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Catalog\Filament\Actions\ApproveServiceAction;
use App\Modules\Catalog\Filament\Actions\BulkApproveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkArchiveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkRejectServicesAction;
use App\Modules\Catalog\Filament\Actions\RejectServiceAction;
use App\Modules\Catalog\Filament\Actions\RequestServiceEditsAction;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages;
use App\Modules\Discovery\Filament\Actions\ReindexServicesAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DigitalServiceResource extends Resource
{
    use Translatable;

    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?string $navigationLabel = 'Digital Services';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.digital_services');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Service::query()
            ->forType(ProductType::Digital)
            ->pendingReview()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.digital_service.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.digital_service.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('product_type', ProductType::Digital);
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Translatable Content')
                ->tabs([
                    Tabs\Tab::make('English')
                        ->schema([
                            TextInput::make('name')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(255)
                                ->label(__('catalog.name')),
                            Textarea::make('short_description')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(1000)
                                ->rows(3)
                                ->label(__('catalog.short_description')),
                            Textarea::make('long_description')
                                ->maxLength(5000)
                                ->rows(5)
                                ->label(__('catalog.long_description')),
                        ]),
                    Tabs\Tab::make('العربية')
                        ->schema([
                            TextInput::make('name')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(255)
                                ->label(__('catalog.name')),
                            Textarea::make('short_description')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(1000)
                                ->rows(3)
                                ->label(__('catalog.short_description')),
                            Textarea::make('long_description')
                                ->maxLength(5000)
                                ->rows(5)
                                ->label(__('catalog.long_description')),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('catalog.shared'))
                ->schema([
                    Select::make('category_id')
                        ->relationship('category', 'name->en')
                        ->searchable()
                        ->preload()
                        ->markAsRequired()
                        ->rule('required')
                        ->label(__('catalog.category')),
                    TextInput::make('base_price_minor')
                        ->label(__('catalog.base_price'))
                        ->helperText('In piastres â€” 10000 = 100.00 EGP')
                        ->numeric()
                        ->minValue(0)
                        ->markAsRequired()
                        ->rule('required'),
                    Select::make('status')
                        ->options(ServiceStatus::class)
                        ->default(ServiceStatus::Draft->value)
                        ->disabled()
                        ->dehydrated(false)
                        ->label(__('catalog.status_label')),
                    Toggle::make('is_featured')
                        ->label(__('catalog.is_featured')),
                ])
                ->columns(2),

            Section::make(__('catalog.digital_details'))
                ->relationship('digitalDetail')
                ->schema([
                    Select::make('delivery_method')
                        ->options([
                            'email' => 'Email',
                            'sms' => 'SMS',
                            'whatsapp' => 'WhatsApp',
                            'link' => 'Link',
                        ])
                        ->markAsRequired()
                        ->rule('required')
                        ->label(__('catalog.delivery_method')),
                    Toggle::make('has_expiry')
                        ->label(__('catalog.has_expiry'))
                        ->live(),
                    TextInput::make('expiry_days_after_purchase')
                        ->numeric()
                        ->minValue(1)
                        ->label(__('catalog.expiry_days_after_purchase'))
                        ->visible(fn ($get): bool => (bool) $get('has_expiry')),
                    Toggle::make('is_refundable_after_delivery')
                        ->label(__('catalog.is_refundable_after_delivery')),
                    TextInput::make('redemption_url_template')
                        ->maxLength(1000)
                        ->url()
                        ->label(__('catalog.redemption_url_template'))
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
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state) => $state->label())
                    ->label(__('catalog.product_type')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => $state instanceof ServiceState
                        ? (ServiceStatus::tryFrom($state->getValue())?->color() ?? 'gray')
                        : 'gray')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ServiceState
                        ? (ServiceStatus::tryFrom($state->getValue())?->label() ?? $state->getValue())
                        : (string) $state)
                    ->label(__('catalog.status_label')),
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
            ->headerActions([
                ReindexServicesAction::make(),
                Action::make('importDigitalServices')
                    ->label('Import Digital Services')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('vendor_profile_id')
                            ->label('Vendor')
                            ->options(
                                fn (): array => VendorProfile::query()
                                    ->where('approval_status', 'approved')
                                    ->get()
                                    ->mapWithKeys(fn (VendorProfile $vp): array => [
                                        $vp->id => is_array($vp->business_name)
                                            ? ($vp->business_name['en'] ?? '')
                                            : (string) $vp->business_name,
                                    ])
                                    ->all()
                            )
                            ->searchable()
                            ->required(),
                        FileUpload::make('file')
                            ->label('Excel File (.xlsx / .xls)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->disk('local')
                            ->directory('excel-imports-temp')
                            ->required(),
                    ])
                    ->action(function (array $data, ImportDigitalServicesFromExcelAction $action): void {
                        $absolutePath = Storage::disk('local')->path($data['file']);
                        $file = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);

                        $import = $action->execute($file, (int) $data['vendor_profile_id'], app()->getLocale());

                        if ($import->status === 'completed') {
                            Notification::make()
                                ->title("Import completed: {$import->imported_rows} services created.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Import failed â€” check per-row errors on the import record.')
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading('Import Digital Services')
                    ->modalSubmitActionLabel('Import')
                    ->requiresConfirmation(false),
            ])
            ->actions([
                ApproveServiceAction::make(),
                RejectServiceAction::make(),
                RequestServiceEditsAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([
                BulkApproveServicesAction::make(),
                BulkRejectServicesAction::make(),
                BulkArchiveServicesAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDigitalServices::route('/'),
            'pending' => Pages\PendingDigitalServicesPage::route('/pending-review'),
            'create' => Pages\CreateDigitalService::route('/create'),
            'edit' => Pages\EditDigitalService::route('/{record}/edit'),
        ];
    }
}
