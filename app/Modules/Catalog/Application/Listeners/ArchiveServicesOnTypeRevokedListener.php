<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Listeners;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Events\VendorTypeRevoked;

class ArchiveServicesOnTypeRevokedListener
{
    public function handle(VendorTypeRevoked $event): void
    {
        Service::query()
            ->where('vendor_profile_id', $event->vendorProfileId)
            ->where('product_type', $event->productType->value)
            ->whereIn('status', [ServiceStatus::Draft->value, ServiceStatus::Published->value])
            ->update(['status' => ServiceStatus::Archived->value]);
    }
}
