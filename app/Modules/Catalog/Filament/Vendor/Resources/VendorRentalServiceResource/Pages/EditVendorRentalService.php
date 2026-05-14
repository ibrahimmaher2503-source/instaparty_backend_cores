<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\UpdateRentalServiceAction;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVendorRentalService extends EditRecord
{
    protected static string $resource = VendorRentalServiceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Service $service */
        $service = $this->record->load('rentalDetail');

        return [
            'name' => [
                'en' => $service->getTranslation('name', 'en'),
                'ar' => $service->getTranslation('name', 'ar'),
            ],
            'short_description' => [
                'en' => $service->getTranslation('short_description', 'en') ?? '',
                'ar' => $service->getTranslation('short_description', 'ar') ?? '',
            ],
            'long_description' => [
                'en' => $service->getTranslation('long_description', 'en') ?? '',
                'ar' => $service->getTranslation('long_description', 'ar') ?? '',
            ],
            'category_id' => $service->category_id,
            'base_price_minor' => $service->base_price_minor,
            'rentalDetail' => [
                'requires_electricity' => $service->rentalDetail?->requires_electricity ?? false,
                'requires_outdoor_space' => $service->rentalDetail?->requires_outdoor_space ?? false,
                'default_rental_duration_hours' => $service->rentalDetail?->default_rental_duration_hours ?? 1,
                'setup_time_minutes' => $service->rentalDetail?->setup_time_minutes ?? 0,
                'teardown_time_minutes' => $service->rentalDetail?->teardown_time_minutes ?? 0,
                'security_deposit_minor' => $service->rentalDetail?->security_deposit_minor ?? 0,
                'minimum_space_sqm' => $service->rentalDetail?->minimum_space_sqm,
            ],
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $vendorProfile = auth()->user()->vendorProfile;

        $payload = [
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'short_description' => $data['short_description'] ?? ['en' => '', 'ar' => ''],
            'long_description' => $data['long_description'] ?? ['en' => '', 'ar' => ''],
            'base_price_minor' => (int) $data['base_price_minor'],
            'requires_electricity' => (bool) ($data['rentalDetail']['requires_electricity'] ?? false),
            'requires_outdoor_space' => (bool) ($data['rentalDetail']['requires_outdoor_space'] ?? false),
            'default_rental_duration_hours' => (int) ($data['rentalDetail']['default_rental_duration_hours'] ?? 1),
            'setup_time_minutes' => isset($data['rentalDetail']['setup_time_minutes']) ? (int) $data['rentalDetail']['setup_time_minutes'] : null,
            'teardown_time_minutes' => isset($data['rentalDetail']['teardown_time_minutes']) ? (int) $data['rentalDetail']['teardown_time_minutes'] : null,
            'security_deposit_minor' => isset($data['rentalDetail']['security_deposit_minor']) ? (int) $data['rentalDetail']['security_deposit_minor'] : null,
            'minimum_space_sqm' => isset($data['rentalDetail']['minimum_space_sqm']) ? (int) $data['rentalDetail']['minimum_space_sqm'] : null,
        ];

        /** @var Service $record */
        return app(UpdateRentalServiceAction::class)->execute($record, $vendorProfile, $payload);
    }

    protected function getSavedNotification(): ?Notification
    {
        $service = $this->record->fresh();

        if ($service->status === ServiceStatus::PendingReview) {
            return Notification::make()
                ->title(__('catalog.vendor.cannot_edit_published'))
                ->warning();
        }

        return Notification::make()
            ->title(__('vendor-portal.services.saved'))
            ->success();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageAvailability')
                ->label(__('catalog.vendor.block_dates'))
                ->icon('heroicon-o-calendar-days')
                ->url(fn () => route('filament.vendor.pages.vendor-service-availability', ['service' => $this->record->public_id])),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
