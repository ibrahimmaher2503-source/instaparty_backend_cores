<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id'               => $this->public_id,
            'rating'                  => $this->rating,
            'body'                    => $this->body,
            'locale'                  => $this->locale,
            'moderation_status'       => $this->moderation_status,
            'vendor_profile_id'       => $this->vendor_profile_id,
            'booking_vendor_public_id'=> $this->whenLoaded('bookingVendor', fn () => $this->bookingVendor?->public_id),
            'created_at'              => $this->created_at?->toISOString(),
        ];
    }
}
