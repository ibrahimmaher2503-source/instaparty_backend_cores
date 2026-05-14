<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Application\Actions\BlockServiceDatesAction;
use App\Modules\Catalog\Application\Actions\UnblockServiceDatesAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorServiceAvailabilityPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-service-availability';

    public string $servicePublicId;

    public ?Service $service = null;

    public function mount(string $service): void
    {
        $this->servicePublicId = $service;
        $this->service = Service::query()
            ->where('public_id', $service)
            ->where('vendor_profile_id', $this->getVendorProfile()->id)
            ->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        $name = $this->service?->getTranslation('name', app()->getLocale()) ?? '';

        return __('catalog.vendor.availability_title').': '.$name;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ServiceAvailabilityBlock::query()
                    ->where('service_id', $this->service->id)
                    ->latest('starts_at')
            )
            ->columns([
                TextColumn::make('starts_at')
                    ->label(__('vendor-portal.availability.starts_at'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('vendor-portal.availability.ends_at'))
                    ->dateTime('d M Y H:i'),
                TextColumn::make('reason')
                    ->label(__('vendor-portal.availability.reason'))
                    ->formatStateUsing(fn (ServiceAvailabilityBlock $record) => $record->getTranslation('reason', app()->getLocale()) ?? '—')
                    ->limit(50),
            ])
            ->actions([
                TableAction::make('unblock')
                    ->label(__('catalog.vendor.unblock'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ServiceAvailabilityBlock $record): void {
                        app(UnblockServiceDatesAction::class)->execute($record, $this->getVendorProfile());
                        Notification::make()->title(__('catalog.vendor.unblocked'))->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('blockDates')
                ->label(__('catalog.vendor.block_dates'))
                ->icon('heroicon-o-calendar-x-mark')
                ->color('warning')
                ->form([
                    DateTimePicker::make('starts_at')
                        ->label(__('vendor-portal.availability.starts_at'))
                        ->required()
                        ->native(false)
                        ->seconds(false),
                    DateTimePicker::make('ends_at')
                        ->label(__('vendor-portal.availability.ends_at'))
                        ->required()
                        ->native(false)
                        ->seconds(false)
                        ->after('starts_at'),
                    Textarea::make('reason_en')
                        ->label(__('vendor-portal.availability.reason_en'))
                        ->rows(2),
                    Textarea::make('reason_ar')
                        ->label(__('vendor-portal.availability.reason_ar'))
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    app(BlockServiceDatesAction::class)->execute(
                        $this->service,
                        $this->getVendorProfile(),
                        startsAt: $data['starts_at'],
                        endsAt: $data['ends_at'],
                        reason: [
                            'en' => $data['reason_en'] ?? null,
                            'ar' => $data['reason_ar'] ?? null,
                        ],
                    );
                    Notification::make()->title(__('catalog.vendor.blocked'))->success()->send();
                }),

            Action::make('back')
                ->label(__('vendor-portal.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => Filament::getPanel('vendor')->getUrl()),
        ];
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
