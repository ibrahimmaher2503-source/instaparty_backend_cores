<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;

class GetVendorApprovalStatusAction
{
    /**
     * @return array{
     *   overall: string,
     *   rejected_reason: array<string, string>|null,
     *   types: array<string, array{approved: bool, approved_at: string|null}>
     * }
     */
    public function execute(VendorProfile $vendorProfile): array
    {
        $approvedTypes = $vendorProfile->approvedTypes()
            ->get()
            ->keyBy(fn (VendorApprovedProductType $row) => $row->product_type->value);

        $types = [];
        foreach (ProductType::cases() as $type) {
            $row = $approvedTypes->get($type->value);
            $types[$type->value] = [
                'approved'    => $row !== null,
                'approved_at' => $row?->approved_at?->toDateTimeString(),
            ];
        }

        return [
            'overall'         => $vendorProfile->approval_status->value,
            'rejected_reason' => $vendorProfile->rejection_reason,
            'types'           => $types,
        ];
    }
}
