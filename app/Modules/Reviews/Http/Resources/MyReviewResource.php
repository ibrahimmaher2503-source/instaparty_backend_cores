<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reviewType = $this->additional['review_type'] ?? (isset($this->service_id) ? 'service' : 'vendor');

        return [
            'review_type'       => $reviewType,
            'public_id'         => $this->public_id,
            'rating'            => $this->rating,
            'body'              => $this->body,
            'locale'            => $this->locale,
            'moderation_status' => $this->moderation_status,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
