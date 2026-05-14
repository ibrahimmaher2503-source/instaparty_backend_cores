<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondToReviewAction
{
    public function execute(
        int $reviewId,
        ReviewType $reviewType,
        VendorProfile $vendorProfile,
        string $body,
        string $locale = 'en',
    ): ReviewResponse {
        if (empty(trim($body))) {
            throw ValidationException::withMessages(['body' => 'Response body is required.']);
        }

        return DB::transaction(fn () => ReviewResponse::updateOrCreate(
            [
                'review_type' => $reviewType->value,
                'review_id' => $reviewId,
                'vendor_profile_id' => $vendorProfile->id,
            ],
            [
                'body' => $body,
                'locale' => $locale,
                'moderation_status' => ModerationStatus::Pending,
            ]
        ));
    }
}
