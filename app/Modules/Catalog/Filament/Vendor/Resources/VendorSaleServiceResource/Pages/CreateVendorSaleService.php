<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\CreateSaleServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVendorSaleService extends CreateRecord
{
    protected static string $resource = VendorSaleServiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $vendorProfile = auth()->user()->vendorProfile;

        $dto = new CreateSaleServiceDTO(
            vendorProfileId: $vendorProfile->id,
            categoryId: (int) $data['category_id'],
            name: $data['name'],
            shortDescription: $data['short_description'] ?? ['en' => '', 'ar' => ''],
            basePriceMinor: (int) $data['base_price_minor'],
            isPerishable: (bool) ($data['saleDetail']['is_perishable'] ?? false),
            isMadeToOrder: (bool) ($data['saleDetail']['is_made_to_order'] ?? false),
            leadTimeHours: isset($data['saleDetail']['lead_time_hours']) ? (int) $data['saleDetail']['lead_time_hours'] : null,
            stockQuantity: isset($data['saleDetail']['stock_quantity']) ? (int) $data['saleDetail']['stock_quantity'] : null,
            customizationFields: $data['saleDetail']['customization_fields'] ?? null,
        );

        return app(CreateSaleServiceAction::class)->execute($dto);
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
