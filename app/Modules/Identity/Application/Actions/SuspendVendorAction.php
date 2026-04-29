<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class SuspendVendorAction
{
    public function execute(VendorProfile $vendorProfile): VendorProfile
    {
        $authId = auth()->id();
        $actorId = $authId !== null ? (int) $authId : null;

        return DB::transaction(function () use ($vendorProfile, $actorId): VendorProfile {
            $old = ['approval_status' => $vendorProfile->approval_status->value];

            $vendorProfile->update([
                'approval_status' => ApprovalStatus::Suspended,
                'suspended_at' => now(),
                'suspended_by' => $actorId,
            ]);

            activity()
                ->on($vendorProfile)
                ->causedBy(auth()->user())
                ->withProperties(['old' => $old, 'new' => ['approval_status' => ApprovalStatus::Suspended->value]])
                ->log('suspended_vendor');

            DB::afterCommit(fn () => event(new VendorSuspended($vendorProfile, $actorId)));

            return $vendorProfile;
        });
    }
}
