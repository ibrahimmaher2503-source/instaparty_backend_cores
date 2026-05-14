<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnblockServiceDatesAction
{
    public function execute(ServiceAvailabilityBlock $block, VendorProfile $vendorProfile): void
    {
        $service = $block->service;

        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['block' => __('catalog.errors.not_owned')]);
        }

        DB::transaction(fn () => $block->delete());
    }
}
