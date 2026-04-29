<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Events\VendorTypeRevoked;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Enums\ProductType;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RevokeVendorTypeAction
{
    public function execute(VendorProfile $vendorProfile, ProductType $productType, array $revokeReason = [], ?int $actorId = null): VendorApprovedProductType
    {
        $row = VendorApprovedProductType::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->where('product_type', $productType)
            ->active()
            ->first();

        if ($row === null) {
            throw new UnprocessableEntityHttpException('Type approval not found or already revoked.');
        }

        $actorId ??= auth()->id();
        $causer = $actorId !== null ? User::find($actorId) : null;

        return DB::transaction(function () use ($vendorProfile, $productType, $revokeReason, $row, $actorId, $causer): VendorApprovedProductType {
            $row->update([
                'revoked_at' => now(),
                'revoked_by' => $actorId,
                'revoke_reason' => $revokeReason,
            ]);

            $permissions = [
                "service.create.{$productType->value}.own",
                "service.update.{$productType->value}.own",
                "service.delete.{$productType->value}.own",
                "service.publish.{$productType->value}.own",
            ];

            User::findOrFail($vendorProfile->user_id)->revokePermissionTo($permissions);

            $log = activity()->on($vendorProfile);
            if ($causer !== null) {
                $log = $log->causedBy($causer);
            }
            $log->withProperties(['product_type' => $productType->value, 'permissions_revoked' => $permissions, 'reason' => $revokeReason])
                ->log('revoked_vendor_type');

            DB::afterCommit(fn () => event(new VendorTypeRevoked($row)));

            return $row;
        });
    }
}
