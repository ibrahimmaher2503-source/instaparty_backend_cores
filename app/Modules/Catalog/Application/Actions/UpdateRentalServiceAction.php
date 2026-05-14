<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRentalServiceAction
{
    private const MATERIAL_FIELDS = ['category_id', 'base_price_minor'];

    public function execute(Service $service, VendorProfile $vendorProfile, array $data): Service
    {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        $isMaterialEdit = $service->status === ServiceStatus::Published
            && count(array_intersect(array_keys($data), self::MATERIAL_FIELDS)) > 0;

        return DB::transaction(function () use ($service, $data, $isMaterialEdit): Service {
            $serviceData = array_intersect_key($data, array_flip([
                'category_id', 'name', 'short_description', 'long_description', 'base_price_minor',
            ]));

            if ($isMaterialEdit) {
                $serviceData['status'] = ServiceStatus::PendingReview;
            }

            if (! empty($serviceData)) {
                $service->update($serviceData);
            }

            $detailData = array_intersect_key($data, array_flip([
                'requires_electricity', 'requires_outdoor_space', 'default_rental_duration_hours',
                'setup_time_minutes', 'teardown_time_minutes', 'security_deposit_minor', 'minimum_space_sqm',
            ]));

            if (! empty($detailData) && $service->rentalDetail !== null) {
                $service->rentalDetail()->update($detailData);
            }

            return $service->refresh();
        });
    }
}
