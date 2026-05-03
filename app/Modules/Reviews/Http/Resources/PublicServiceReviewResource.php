<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicServiceReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');
        $locale = str_starts_with($locale, 'ar') ? 'ar' : 'en';

        $fallback = $locale === 'ar'
            ? __('reviews::reviews.verified_customer_ar')
            : __('reviews::reviews.verified_customer');

        $reviewer = $this->reviewer;
        $firstName = $fallback;
        if ($reviewer && ! empty($reviewer->name)) {
            $parts = preg_split('/\s+/', trim($reviewer->name), 2);
            $firstName = $parts[0] ?? $fallback;
        }

        return [
            'public_id'            => $this->public_id,
            'rating'               => $this->rating,
            'body'                 => $this->body,
            'locale'               => $this->locale,
            'reviewer_first_name'  => $firstName,
            'created_at'           => $this->created_at?->toISOString(),
        ];
    }
}
