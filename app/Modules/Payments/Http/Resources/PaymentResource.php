<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response 200 {
 *  "data": {"public_id":"01J9X7K3M2WZE0X8H4Q9N5T7V2","amount_minor":50000,"amount_currency":"EGP"},
 *  "meta":{"locale":"en","direction":"ltr"},
 *  "errors":[]
 * }
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'public_id' => $this->public_id,
            'booking_id' => $this->booking_id,
            'amount_minor' => $this->amount_minor,
            'amount_currency' => $this->amount_currency,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'failure_message' => $this->failure_message[$locale] ?? null,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
