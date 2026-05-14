<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListVendorServicesAction
{
    public function execute(
        VendorProfile $vendorProfile,
        ?ProductType $type = null,
        ?ServiceStatus $status = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->when($type !== null, fn (Builder $q) => $q->where('product_type', $type->value))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status->value))
            ->with(['category', 'rentalDetail', 'saleDetail', 'digitalDetail'])
            ->latest()
            ->paginate($perPage);
    }
}
