<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Actions;

use App\Modules\Reviews\Application\DTOs\ModerateReviewData;
use App\Modules\Reviews\Domain\Contracts\ReviewModerationLogRepository;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Events\ReviewApproved;
use App\Modules\Reviews\Domain\Events\ReviewHidden;
use App\Modules\Reviews\Domain\Events\ReviewRejected;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ModerateReviewAction
{
    public function __construct(
        private readonly ServiceReviewRepository $serviceRepository,
        private readonly VendorReviewRepository $vendorRepository,
        private readonly ReviewModerationLogRepository $logRepository,
    ) {}

    public function execute(ServiceReview|VendorReview $review, ModerateReviewData $data): ServiceReview|VendorReview
    {
        $previousStatus = $review->moderation_status;

        if (! $previousStatus->canTransitionTo($data->toStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition review from '{$previousStatus->value}' to '{$data->toStatus->value}'."
            );
        }

        $repository = $review instanceof ServiceReview ? $this->serviceRepository : $this->vendorRepository;
        $reviewType = $review instanceof ServiceReview ? 'service' : 'vendor';
        $subjectId = $review instanceof ServiceReview ? $review->service_id : $review->vendor_profile_id;

        DB::transaction(function () use ($review, $data, $previousStatus, $repository, $reviewType): void {
            $repository->transition($review, $data->toStatus->value, $data->moderatorId);

            $this->logRepository->append(
                reviewType: $reviewType,
                reviewId: $review->id,
                fromStatus: $previousStatus->value,
                toStatus: $data->toStatus->value,
                moderatorId: $data->moderatorId,
                reason: $data->reason,
            );
        });

        DB::afterCommit(function () use ($review, $data, $previousStatus, $subjectId): void {
            $event = match ($data->toStatus) {
                ModerationStatus::Approved => new ReviewApproved(
                    reviewId: $review->id,
                    reviewPublicId: $review->public_id,
                    reviewType: $review instanceof ServiceReview ? 'service' : 'vendor',
                    subjectId: $subjectId,
                    rating: $review->rating,
                    approvedAt: now(),
                    moderatorId: $data->moderatorId,
                    previousStatus: $previousStatus->value,
                ),
                ModerationStatus::Rejected => new ReviewRejected(
                    reviewId: $review->id,
                    reviewPublicId: $review->public_id,
                    reviewType: $review instanceof ServiceReview ? 'service' : 'vendor',
                    subjectId: $subjectId,
                    reason: $data->reason,
                    moderatorId: $data->moderatorId,
                    rejectedAt: now(),
                    previousStatus: $previousStatus->value,
                ),
                ModerationStatus::Hidden => new ReviewHidden(
                    reviewId: $review->id,
                    reviewPublicId: $review->public_id,
                    reviewType: $review instanceof ServiceReview ? 'service' : 'vendor',
                    subjectId: $subjectId,
                    moderatorId: $data->moderatorId,
                    hiddenAt: now(),
                ),
                default => null,
            };

            if ($event !== null) {
                event($event);
            }
        });

        return $review->fresh();
    }
}
