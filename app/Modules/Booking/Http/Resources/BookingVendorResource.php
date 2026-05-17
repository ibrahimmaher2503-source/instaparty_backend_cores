<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingVendor
 *
 * @response {
 *   "data": {
 *     "public_id": "01HXY...",
 *     "sub_status": "modified",
 *     "subtotal_minor": 25000,
 *     "active_modification_proposal": {
 *       "public_id": "01HXZ...",
 *       "proposal_kind": "change_price",
 *       "vendor_explanation": "Higher setup fee",
 *       "expires_at": "2026-05-18T12:00:00+00:00",
 *       "total_price_delta_minor": 5000,
 *       "change_count": 1
 *     }
 *   }
 * }
 */
class BookingVendorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = str_starts_with($request->header('Accept-Language', 'ar'), 'en') ? 'en' : 'ar';

        return [
            'public_id' => $this->public_id,
            'vendor_profile_id' => $this->vendor_profile_id,
            'sub_status' => $this->sub_status->value,
            'response_deadline' => $this->response_deadline?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'subtotal_minor' => $this->subtotal_minor,
            'delivery_fee_minor' => $this->delivery_fee_minor,
            'currency' => $this->subtotal_currency ?? 'EGP',
            'active_modification_proposal' => $this->activeModificationProposal($locale),
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
        ];
    }

    /** @return array<string, mixed>|null */
    private function activeModificationProposal(string $locale): ?array
    {
        /** @var BookingVendor $bookingVendor */
        $bookingVendor = $this->resource;

        /** @var BookingModification|null $proposal */
        $proposal = BookingModification::query()
            ->where('booking_vendor_id', $bookingVendor->id)
            ->where('status', ModificationStatus::Pending->value)
            ->withCount('items')
            ->latest('id')
            ->first();

        if ($proposal === null) {
            return null;
        }

        $explanation = $proposal->vendor_explanation ?? [];
        $totals = $proposal->diff_snapshot['totals'] ?? [];

        return [
            'public_id' => $proposal->public_id,
            'proposal_kind' => $proposal->proposal_kind->value,
            'vendor_explanation' => is_array($explanation)
                ? ($explanation[$locale] ?? $explanation['en'] ?? $explanation['ar'] ?? '')
                : '',
            'expires_at' => $proposal->expires_at?->toIso8601String(),
            'total_price_delta_minor' => (int) ($totals['price_delta_minor'] ?? 0),
            'change_count' => (int) ($proposal->items_count ?? 0),
        ];
    }
}
