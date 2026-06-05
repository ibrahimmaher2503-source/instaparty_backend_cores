<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Domain\Models\BookingModification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookingModification */
class BookingModificationResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'booking_vendor_id' => $this->bookingVendor?->public_id,
            // 'admin' marks an admin-proposed alternative; 'vendor' a vendor
            // modification. The client renders the two distinctly.
            'proposed_by_role' => $this->proposerRole(),
            'proposal_kind' => $this->proposal_kind->value,
            'status' => $this->status->value,
            'vendor_explanation' => $this->vendor_explanation,
            'diff_snapshot' => $this->diff_snapshot,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function proposerRole(): string
    {
        $proposer = $this->proposedBy;

        if ($proposer !== null && $proposer->hasRole('admin')) {
            return 'admin';
        }

        return 'vendor';
    }
}
