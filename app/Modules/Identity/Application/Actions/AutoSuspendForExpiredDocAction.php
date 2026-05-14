<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\ComplianceEventType;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorComplianceEvent;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutoSuspendForExpiredDocAction
{
    public function execute(VendorDocument $document): VendorProfile
    {
        return DB::transaction(function () use ($document) {
            $vendorProfile = $document->vendorProfile;

            $docType = __('identity.document_type.'.$document->doc_type->value);
            $expiryDate = $document->expires_at->format('Y-m-d');

            $suspensionReason = [
                'en' => "Automatically suspended: {$docType} expired on {$expiryDate}",
                'ar' => "تم التعليق تلقائياً: وثيقة {$docType} انتهت صلاحيتها في {$expiryDate}",
            ];

            $vendorProfile->update([
                'approval_status' => 'suspended',
                'suspended_at' => now(),
                'suspended_by' => null,
                'suspension_reason' => $suspensionReason,
            ]);

            VendorComplianceEvent::create([
                'public_id' => Str::ulid(),
                'vendor_profile_id' => $vendorProfile->id,
                'document_id' => $document->id,
                'event_type' => ComplianceEventType::AutoSuspended,
                'admin_id' => null,
                'occurred_at' => now(),
                'reason' => $suspensionReason,
            ]);

            VendorApprovedProductType::where('vendor_profile_id', $vendorProfile->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_by' => null,
                    'revoke_reason' => $suspensionReason,
                ]);

            DB::afterCommit(fn () => event(new VendorAutoSuspended($vendorProfile, $document)));

            return $vendorProfile->refresh();
        });
    }
}
