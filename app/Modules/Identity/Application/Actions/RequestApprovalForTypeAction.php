<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestApprovalForTypeAction
{
    public function execute(VendorProfile $vendorProfile, ProductType $productType): void
    {
        if ($vendorProfile->approval_status !== ApprovalStatus::Approved) {
            throw ValidationException::withMessages([
                'approval_status' => __('identity.errors.profile_not_approved'),
            ]);
        }

        $alreadyApproved = $vendorProfile->approvedTypes()
            ->where('product_type', $productType->value)
            ->exists();

        if ($alreadyApproved) {
            throw ValidationException::withMessages([
                'product_type' => __('identity.errors.type_already_approved'),
            ]);
        }

        DB::transaction(function () use ($vendorProfile, $productType): void {
            DB::table('audit_logs')->insert([
                'public_id'      => (string) Str::ulid(),
                'auditable_type' => VendorProfile::class,
                'auditable_id'   => $vendorProfile->id,
                'user_id'        => $vendorProfile->user_id,
                'action'         => 'vendor_approval_requested',
                'changes'        => json_encode(['product_type' => $productType->value]),
                'ip_address'     => Request::ip(),
                'user_agent'     => Request::userAgent(),
                'created_at'     => now(),
            ]);
        });
    }
}
