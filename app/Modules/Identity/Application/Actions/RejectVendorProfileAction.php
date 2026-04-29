<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorRejected;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class RejectVendorProfileAction
{
    public function execute(VendorProfile $vendorProfile, array $rejectionReason = []): VendorProfile
    {
        $authId = auth()->id();
        $actorId = $authId !== null ? (int) $authId : null;

        return DB::transaction(function () use ($vendorProfile, $rejectionReason, $actorId): VendorProfile {
            $old = ['approval_status' => $vendorProfile->approval_status->value];

            $vendorProfile->update([
                'approval_status' => ApprovalStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => $actorId,
                'rejection_reason' => $rejectionReason,
            ]);

            activity()
                ->on($vendorProfile)
                ->causedBy(auth()->user())
                ->withProperties(['old' => $old, 'new' => ['approval_status' => ApprovalStatus::Rejected->value, 'rejection_reason' => $rejectionReason]])
                ->log('rejected_vendor_profile');

            DB::afterCommit(fn () => event(new VendorRejected($vendorProfile, $actorId, $rejectionReason)));

            return $vendorProfile;
        });
    }
}
