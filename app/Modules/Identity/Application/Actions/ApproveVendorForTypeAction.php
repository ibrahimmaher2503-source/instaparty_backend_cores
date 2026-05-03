<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorApprovedForType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ApproveVendorForTypeAction
{
    public function execute(VendorProfile $vendorProfile, ProductType $productType): VendorApprovedProductType
    {
        if ($vendorProfile->approval_status !== ApprovalStatus::Approved) {
            throw new UnprocessableEntityHttpException('Vendor profile must be approved before granting per-type permissions.');
        }

        return DB::transaction(function () use ($vendorProfile, $productType): VendorApprovedProductType {
            $row = VendorApprovedProductType::create([
                'vendor_profile_id' => $vendorProfile->id,
                'product_type' => $productType,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            $permissions = [
                "service.create.{$productType->value}.own",
                "service.update.{$productType->value}.own",
                "service.delete.{$productType->value}.own",
                "service.publish.{$productType->value}.own",
            ];

            User::findOrFail($vendorProfile->user_id)->givePermissionTo($permissions);

            activity()
                ->on($vendorProfile)
                ->causedBy(auth()->user())
                ->withProperties(['product_type' => $productType->value, 'permissions_granted' => $permissions])
                ->log('approved_vendor_for_type');

            DB::afterCommit(fn () => event(new VendorApprovedForType($row)));

            return $row;
        });
    }
}
