<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Actions;

use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 13.3 — customer edits their own review BEFORE moderation. Once a
 * moderator has approved/rejected/hidden it, the text is frozen (the
 * moderation decision applied to specific content). Editing keeps the
 * review in the pending queue — no status reset needed.
 */
class UpdateOwnReviewAction
{
    public function execute(ServiceReview|VendorReview $review, int $userId, ?int $rating, ?string $body): ServiceReview|VendorReview
    {
        if ($review->user_id !== $userId) {
            throw new AuthorizationException('You do not own this review.');
        }

        if ($review->moderation_status->value !== 'pending') {
            throw new UnprocessableEntityHttpException(__('reviews::reviews.errors.locked_after_moderation'));
        }

        return DB::transaction(function () use ($review, $rating, $body): ServiceReview|VendorReview {
            $review->fill(array_filter([
                'rating' => $rating,
                'body' => $body,
            ], fn ($v) => $v !== null))->save();

            return $review;
        });
    }
}
