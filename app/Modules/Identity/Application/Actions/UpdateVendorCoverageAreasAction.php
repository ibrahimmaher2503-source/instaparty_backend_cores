<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class UpdateVendorCoverageAreasAction
{
    /**
     * Full replace of vendor coverage areas. Each element must have:
     * city_id, delivery_fee_minor, min_order_minor (currencies default EGP).
     *
     * @param  array<int, array{city_id: int, delivery_fee_minor: int, min_order_minor: int}>  $areas
     */
    public function execute(VendorProfile $vendorProfile, array $areas): void
    {
        DB::transaction(function () use ($vendorProfile, $areas): void {
            $vendorProfile->coverageAreas()->delete();

            foreach ($areas as $area) {
                $vendorProfile->coverageAreas()->create([
                    'city_id'               => $area['city_id'],
                    'delivery_fee_minor'    => $area['delivery_fee_minor'] ?? 0,
                    'delivery_fee_currency' => 'EGP',
                    'min_order_minor'       => $area['min_order_minor'] ?? 0,
                    'min_order_currency'    => 'EGP',
                ]);
            }
        });
    }
}
