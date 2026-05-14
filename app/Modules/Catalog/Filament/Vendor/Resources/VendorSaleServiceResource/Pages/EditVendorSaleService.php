<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\UpdateSaleServiceAction;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVendorSaleService extends EditRecord
{
    protected static string $resource = VendorSaleServiceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Service $service */
        $service = $this->record->load('saleDetail');

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
            'saleDetail' => [
                'is_perishable' => $service->saleDetail?->is_perishable ?? false,
                'is_made_to_order' => $service->saleDetail?->is_made_to_order ?? false,
                'lead_time_hours' => $service->saleDetail?->lead_time_hours,
                'stock_quantity' => $service->saleDetail?->stock_quantity,
                'customization_fields' => $service->saleDetail?->customization_fields,
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
            'is_perishable' => (bool) ($data['saleDetail']['is_perishable'] ?? false),
            'is_made_to_order' => (bool) ($data['saleDetail']['is_made_to_order'] ?? false),
            'lead_time_hours' => isset($data['saleDetail']['lead_time_hours']) ? (int) $data['saleDetail']['lead_time_hours'] : null,
            'stock_quantity' => isset($data['saleDetail']['stock_quantity']) ? (int) $data['saleDetail']['stock_quantity'] : null,
            'customization_fields' => $data['saleDetail']['customization_fields'] ?? null,
        ];

        /** @var Service $record */
        return app(UpdateSaleServiceAction::class)->execute($record, $vendorProfile, $payload);
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
