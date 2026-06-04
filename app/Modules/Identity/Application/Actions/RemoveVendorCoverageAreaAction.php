<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use Illuminate\Support\Facades\DB;

/**
 * Vendor-portal 9.4 — remove a city from the vendor's coverage. Existing
 * bookings are unaffected (delivery fees are snapshotted at booking time).
 */
class RemoveVendorCoverageAreaAction
{
    public function execute(VendorCoverageArea $area): void
    {
        DB::transaction(fn () => $area->delete());
    }
}
