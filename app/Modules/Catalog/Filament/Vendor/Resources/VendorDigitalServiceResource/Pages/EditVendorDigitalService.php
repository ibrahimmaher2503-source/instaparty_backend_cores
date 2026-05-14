<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\UpdateDigitalServiceAction;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVendorDigitalService extends EditRecord
{
    protected static string $resource = VendorDigitalServiceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Service $service */
        $service = $this->record->load('digitalDetail');

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
            'digitalDetail' => [
                'delivery_method' => $service->digitalDetail?->delivery_method,
                'has_expiry' => $service->digitalDetail?->has_expiry ?? false,
                'expiry_days_after_purchase' => $service->digitalDetail?->expiry_days_after_purchase,
                'is_refundable_after_delivery' => $service->digitalDetail?->is_refundable_after_delivery ?? false,
                'redemption_url_template' => $service->digitalDetail?->redemption_url_template,
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
            'delivery_method' => $data['digitalDetail']['delivery_method'] ?? 'email',
            'has_expiry' => (bool) ($data['digitalDetail']['has_expiry'] ?? false),
            'expiry_days_after_purchase' => isset($data['digitalDetail']['expiry_days_after_purchase'])
                ? (int) $data['digitalDetail']['expiry_days_after_purchase']
                : null,
            'is_refundable_after_delivery' => (bool) ($data['digitalDetail']['is_refundable_after_delivery'] ?? false),
            'redemption_url_template' => $data['digitalDetail']['redemption_url_template'] ?? null,
        ];

        /** @var Service $record */
        return app(UpdateDigitalServiceAction::class)->execute($record, $vendorProfile, $payload);
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
