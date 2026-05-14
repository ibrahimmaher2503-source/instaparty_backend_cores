<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorArchiveServiceAction
{
    public function execute(Service $service, VendorProfile $vendorProfile): void
    {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        if (! $service->status->canTransitionTo(ServiceStatus::Archived)) {
            throw ValidationException::withMessages(['status' => __('catalog.moderation_invalid_transition')]);
        }

        DB::transaction(fn () => $service->update(['status' => ServiceStatus::Archived]));
    }
}
