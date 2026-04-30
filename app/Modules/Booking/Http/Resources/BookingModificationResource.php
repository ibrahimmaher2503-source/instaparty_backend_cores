<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Booking\Domain\Models\BookingModification */
class BookingModificationResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id'          => $this->public_id,
            'booking_vendor_id'  => $this->bookingVendor?->public_id,
            'proposal_kind'      => $this->proposal_kind->value,
            'status'             => $this->status->value,
            'vendor_explanation' => $this->vendor_explanation,
            'diff_snapshot'      => $this->diff_snapshot,
            'created_at'         => $this->created_at->toIso8601String(),
        ];
    }
}
