<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlockServiceDatesAction
{
    public function execute(
        Service $service,
        VendorProfile $vendorProfile,
        string $startsAt,
        string $endsAt,
        ?array $reason = null,
    ): ServiceAvailabilityBlock {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        return DB::transaction(fn () => ServiceAvailabilityBlock::create([
            'public_id'  => (string) Str::ulid(),
            'service_id' => $service->id,
            'starts_at'  => $startsAt,
            'ends_at'    => $endsAt,
            'reason'     => $reason,
        ]));
    }
}
