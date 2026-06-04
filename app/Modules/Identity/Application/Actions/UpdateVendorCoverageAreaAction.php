<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use Illuminate\Support\Facades\DB;

/**
 * Vendor-portal 9.3 — update delivery fee / minimum order for one covered
 * city. Ownership enforced by the caller's scoped lookup.
 */
class UpdateVendorCoverageAreaAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(VendorCoverageArea $area, array $attributes): VendorCoverageArea
    {
        return DB::transaction(function () use ($area, $attributes): VendorCoverageArea {
            $area->fill($attributes)->save();

            return $area->refresh();
        });
    }
}
