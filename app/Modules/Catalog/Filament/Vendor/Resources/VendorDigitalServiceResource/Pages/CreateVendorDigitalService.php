<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\CreateDigitalServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVendorDigitalService extends CreateRecord
{
    protected static string $resource = VendorDigitalServiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $vendorProfile = auth()->user()->vendorProfile;

        $dto = new CreateDigitalServiceDTO(
            vendorProfileId: $vendorProfile->id,
            categoryId: (int) $data['category_id'],
            name: $data['name'],
            shortDescription: $data['short_description'] ?? ['en' => '', 'ar' => ''],
            basePriceMinor: (int) $data['base_price_minor'],
            deliveryMethod: $data['digitalDetail']['delivery_method'] ?? 'email',
            hasExpiry: (bool) ($data['digitalDetail']['has_expiry'] ?? false),
            expiryDaysAfterPurchase: isset($data['digitalDetail']['expiry_days_after_purchase'])
                ? (int) $data['digitalDetail']['expiry_days_after_purchase']
                : null,
            isRefundableAfterDelivery: (bool) ($data['digitalDetail']['is_refundable_after_delivery'] ?? false),
            redemptionUrlTemplate: $data['digitalDetail']['redemption_url_template'] ?? null,
        );

        return app(CreateDigitalServiceAction::class)->execute($dto);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title(__('vendor-portal.services.created'))
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
