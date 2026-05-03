<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookingAdminIntervention */
class BookingAdminInterventionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $beforePaymentStatus = $this->resource->before_state['payment_status'] ?? null;

        return [
            'intervention_public_id'  => $this->resource->public_id,
            'booking_public_id'       => $this->resource->booking?->public_id,
            'intervention_type'       => $this->resource->intervention_type->value,
            'before_lifecycle_status' => $this->resource->before_state['lifecycle_status'] ?? null,
            'after_lifecycle_status'  => $this->resource->after_state['lifecycle_status'] ?? null,
            'refund_initiated'        => $beforePaymentStatus === 'paid',
            'created_at'              => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
