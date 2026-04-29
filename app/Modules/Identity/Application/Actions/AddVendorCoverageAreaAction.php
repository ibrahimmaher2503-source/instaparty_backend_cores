<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class AddVendorCoverageAreaAction
{
    public function execute(VendorProfile $vendorProfile, array $data): VendorCoverageArea
    {
        return DB::transaction(fn () => VendorCoverageArea::query()->updateOrCreate(
            [
                'vendor_profile_id' => $vendorProfile->id,
                'city_id' => $data['city_id'],
            ],
            [
                'delivery_fee_minor' => $data['delivery_fee_minor'] ?? 0,
                'delivery_fee_currency' => $data['delivery_fee_currency'] ?? 'EGP',
                'min_order_minor' => $data['min_order_minor'] ?? 0,
                'min_order_currency' => $data['min_order_currency'] ?? 'EGP',
            ]
        ));
    }
}
