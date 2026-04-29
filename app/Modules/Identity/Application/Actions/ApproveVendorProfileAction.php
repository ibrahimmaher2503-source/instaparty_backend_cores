<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorApproved;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class ApproveVendorProfileAction
{
    public function execute(VendorProfile $vendorProfile): VendorProfile
    {
        return DB::transaction(function () use ($vendorProfile): VendorProfile {
            $old = ['approval_status' => $vendorProfile->approval_status->value];

            $vendorProfile->update([
                'approval_status' => ApprovalStatus::Approved,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            activity()
                ->on($vendorProfile)
                ->causedBy(auth()->user())
                ->withProperties(['old' => $old, 'new' => ['approval_status' => ApprovalStatus::Approved->value]])
                ->log('approved_vendor_profile');

            DB::afterCommit(fn () => event(new VendorApproved($vendorProfile)));

            return $vendorProfile;
        });
    }
}
