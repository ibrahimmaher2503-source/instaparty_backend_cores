<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response {
 *   "data": {
 *     "public_id": "01HZ...",
 *     "rating": 5,
 *     "body": "Great service!",
 *     "locale": "en",
 *     "moderation_status": "pending",
 *     "service_public_id": "01HZ...",
 *     "booking_item_public_id": "01HZ...",
 *     "created_at": "2026-05-03T10:00:00Z"
 *   }
 * }
 */
class ServiceReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id'              => $this->public_id,
            'rating'                 => $this->rating,
            'body'                   => $this->body,
            'locale'                 => $this->locale,
            'moderation_status'      => $this->moderation_status,
            'service_public_id'      => $this->whenLoaded('service', fn () => $this->service?->public_id),
            'booking_item_public_id' => $this->whenLoaded('bookingItem', fn () => $this->bookingItem?->public_id),
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }
}
