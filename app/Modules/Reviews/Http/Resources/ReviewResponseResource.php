<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response {
 *   "data": {
 *     "id": 1,
 *     "review_type": "service",
 *     "review_id": 42,
 *     "body": "Thank you for your kind review!",
 *     "locale": "en",
 *     "moderation_status": "pending",
 *     "created_at": "2026-05-14T10:00:00Z"
 *   }
 * }
 */
class ReviewResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review_type' => $this->review_type instanceof \BackedEnum ? $this->review_type->value : $this->review_type,
            'review_id' => $this->review_id,
            'body' => $this->body,
            'locale' => $this->locale instanceof \BackedEnum ? $this->locale->value : $this->locale,
            'moderation_status' => $this->moderation_status instanceof \BackedEnum ? $this->moderation_status->value : $this->moderation_status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
