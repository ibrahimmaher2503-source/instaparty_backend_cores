<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Actions;

use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Domain\Events\ReviewSelfDeleted;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class DeleteOwnReviewAction
{
    public function __construct(
        private readonly ServiceReviewRepository $serviceRepository,
        private readonly VendorReviewRepository $vendorRepository,
    ) {}

    public function execute(ServiceReview|VendorReview $review, int $userId): void
    {
        if ($review->user_id !== $userId) {
            throw new AuthorizationException('You do not own this review.');
        }

        $previousStatus = $review->moderation_status->value;
        $reviewType = $review instanceof ServiceReview ? 'service' : 'vendor';
        $subjectId = $review instanceof ServiceReview ? $review->service_id : $review->vendor_profile_id;
        $repository = $review instanceof ServiceReview ? $this->serviceRepository : $this->vendorRepository;

        DB::transaction(fn () => $repository->softDelete($review));

        DB::afterCommit(fn () => event(new ReviewSelfDeleted(
            reviewId: $review->id,
            reviewType: $reviewType,
            subjectId: $subjectId,
            previousModerationStatus: $previousStatus,
        )));
    }
}
