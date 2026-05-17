<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use Illuminate\Support\Facades\DB;

class CancelServiceChangeRequestAction
{
    public function executeForVendorSuspension(int $vendorProfileId): void
    {
        DB::transaction(function () use ($vendorProfileId): void {
            ServiceChangeRequest::query()
                ->where('vendor_profile_id', $vendorProfileId)
                ->whereIn('status', [
                    ServiceChangeRequestStatus::Pending->value,
                    ServiceChangeRequestStatus::AwaitingClarification->value,
                ])
                ->update([
                    'status' => ServiceChangeRequestStatus::CancelledVendorSuspended->value,
                ]);
        });
    }

    public function executeForServiceArchive(int $serviceId): void
    {
        DB::transaction(function () use ($serviceId): void {
            ServiceChangeRequest::query()
                ->where('service_id', $serviceId)
                ->whereIn('status', [
                    ServiceChangeRequestStatus::Pending->value,
                    ServiceChangeRequestStatus::AwaitingClarification->value,
                ])
                ->update([
                    'status' => ServiceChangeRequestStatus::CancelledServiceUnavailable->value,
                ]);
        });
    }
}
