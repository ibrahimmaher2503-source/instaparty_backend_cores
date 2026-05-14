<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDigitalServiceAction
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
                'delivery_method', 'has_expiry', 'expiry_days_after_purchase',
                'is_refundable_after_delivery', 'redemption_url_template',
            ]));

            if (! empty($detailData) && $service->digitalDetail !== null) {
                $service->digitalDetail()->update($detailData);
            }

            return $service->refresh();
        });
    }
}
