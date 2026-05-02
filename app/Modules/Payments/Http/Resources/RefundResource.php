<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response 200 {
 *  "data": {"public_id":"01J9X7K3M2WZE0X8H4Q9N5T7V2","reason_notes":{"en":"Customer request","ar":"طلب العميل"}},
 *  "meta":{"locale":"en","direction":"ltr"},
 *  "errors":[]
 * }
 */
class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'payment_id' => $this->payment_id,
            'booking_id' => $this->booking_id,
            'amount_minor' => $this->amount_minor,
            'amount_currency' => $this->amount_currency,
            'reason_code' => $this->reason_code->value,
            'reason_notes' => $this->reason_notes,
            'status' => $this->status->value,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
