<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var VendorProfile $profile */
        $profile = $this->resource;
        $locale = app()->getLocale();

        return [
            'id' => $profile->public_id,
            'business_name' => $profile->getTranslation('business_name', $locale),
            'business_name_translations' => $profile->getTranslations('business_name'),
            'slug' => $profile->slug,
            'bio' => $profile->bio ? $profile->getTranslation('bio', $locale) : null,
            'business_type' => $profile->business_type?->value,
            'approval_status' => $profile->approval_status->value,
            'primary_governorate_id' => $profile->primary_governorate_id,
            'primary_city_id' => $profile->primary_city_id,
            'approved_product_types' => $this->whenLoaded(
                'approvedTypes',
                fn () => $profile->approvedTypes
                    ->map(fn (VendorApprovedProductType $row): string => $row->product_type->value)
                    ->values()
            ),
            'created_at' => $profile->created_at->toIso8601String(),
        ];
    }
}
