<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Resources;

use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vendor-facing review inbox item. Wraps either a ServiceReview or a
 * VendorReview and attaches the vendor's own response (with its moderation
 * status) when one exists.
 *
 * Privacy: reviewer exposed as first name only — same rule as the public list.
 */
class VendorReviewInboxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = str_starts_with($request->header('Accept-Language', 'en'), 'ar') ? 'ar' : 'en';

        $isServiceReview = $this->resource instanceof ServiceReview;
        $type = $isServiceReview ? ReviewType::Service : ReviewType::Vendor;

        $fallback = $locale === 'ar'
            ? __('reviews::reviews.verified_customer_ar')
            : __('reviews::reviews.verified_customer');

        $reviewer = $this->reviewer;
        $firstName = $fallback;
        if ($reviewer && ! empty($reviewer->name)) {
            $parts = preg_split('/\s+/', trim($reviewer->name), 2);
            $firstName = $parts[0] ?? $fallback;
        }

        /** @var ReviewResponse|null $response */
        $response = $this->vendorResponse;

        return [
            'public_id' => $this->public_id,
            'review_type' => $type->value,
            'rating' => $this->rating,
            'body' => $this->body,
            'locale' => $this->locale,
            'reviewer_first_name' => $firstName,
            'service' => $this->when(
                $isServiceReview,
                fn () => [
                    'public_id' => $this->service?->public_id,
                    'name' => $this->service?->getTranslation('name', $locale),
                    'product_type' => $this->service?->product_type?->value,
                ],
            ),
            'response' => $response === null ? null : [
                'public_id' => $response->public_id,
                'body' => $response->body,
                'moderation_status' => $response->moderation_status->value,
                'created_at' => $response->created_at?->toISOString(),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
