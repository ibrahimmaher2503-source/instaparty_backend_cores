<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class UpdateVendorProfileAction
{
    private const SENSITIVE_FIELDS = ['bank_iban', 'bank_name', 'bank_account_holder', 'bank_swift_bic'];

    public function execute(VendorProfile $vendorProfile, array $data): VendorProfile
    {
        return DB::transaction(function () use ($vendorProfile, $data): VendorProfile {
            $sensitiveOld = array_intersect_key(
                $vendorProfile->toArray(),
                array_flip(self::SENSITIVE_FIELDS)
            );
            $sensitiveNew = array_intersect_key($data, array_flip(self::SENSITIVE_FIELDS));

            // Update User-level fields
            $userFields = array_filter(['preferred_locale' => $data['preferred_locale'] ?? null]);
            if ($userFields !== []) {
                $vendorProfile->user()->firstOrFail()->update($userFields);
            }

            // Update VendorProfile fields
            $profileFields = array_diff_key($data, array_flip(['preferred_locale']));
            if ($profileFields !== []) {
                $vendorProfile->fill($profileFields)->save();
            }

            if ($sensitiveNew !== []) {
                activity()
                    ->on($vendorProfile)
                    ->causedBy(auth()->user())
                    ->withProperties(['old' => $sensitiveOld, 'new' => $sensitiveNew])
                    ->log('updated_vendor_profile');
            }

            $vendorProfile->refresh();

            return $vendorProfile;
        });
    }
}
