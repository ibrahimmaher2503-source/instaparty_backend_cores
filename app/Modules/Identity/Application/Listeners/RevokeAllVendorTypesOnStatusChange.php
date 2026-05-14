<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Identity\Application\Actions\RevokeVendorTypeAction;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use App\Modules\Identity\Domain\Events\VendorRejected;
use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class RevokeAllVendorTypesOnStatusChange implements ShouldQueue
{
    public function __construct(private readonly RevokeVendorTypeAction $revokeVendorTypeAction) {}

    public function handle(VendorSuspended|VendorRejected|VendorAutoSuspended $event): void
    {
        $vendorProfile = $event->vendorProfile;
        $actorId = $event instanceof VendorAutoSuspended ? null : ($event instanceof VendorSuspended ? $event->suspendedBy : $event->rejectedBy);

        $rows = VendorApprovedProductType::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->active()
            ->get();

        DB::transaction(function () use ($vendorProfile, $rows, $actorId): void {
            $rows->each(function (VendorApprovedProductType $row) use ($vendorProfile, $actorId): void {
                $this->revokeVendorTypeAction->execute(
                    $vendorProfile,
                    $row->product_type,
                    ['reason' => 'auto_revoked_on_status_change'],
                    $actorId,
                );
            });
        });
    }
}
