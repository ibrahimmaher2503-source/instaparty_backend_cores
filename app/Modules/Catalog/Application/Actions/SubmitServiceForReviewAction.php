<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Events\ServiceSubmittedForReview;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitServiceForReviewAction
{
    public function execute(Service $service, VendorProfile $vendorProfile): Service
    {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        if (! in_array($service->status, [ServiceStatus::Draft, ServiceStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages([
                'status' => __('catalog.errors.cannot_submit'),
            ]);
        }

        return DB::transaction(function () use ($service): Service {
            $service->update(['status' => ServiceStatus::PendingReview]);

            DB::afterCommit(fn () => event(new ServiceSubmittedForReview($service->id)));

            return $service;
        });
    }
}
