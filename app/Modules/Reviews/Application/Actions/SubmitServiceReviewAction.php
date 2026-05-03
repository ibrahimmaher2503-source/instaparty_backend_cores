<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Actions;

use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Domain\Contracts\BookingItemReviewabilityReader;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Events\ReviewSubmitted;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SubmitServiceReviewAction
{
    public function __construct(
        private readonly BookingItemReviewabilityReader $eligibilityReader,
        private readonly ServiceReviewRepository $repository,
    ) {}

    public function execute(SubmitReviewData $data): ServiceReview
    {
        $context = $this->eligibilityReader->resolveReviewableContext($data->bookingSubjectPublicId, $data->userId);

        if ($context === null) {
            throw new HttpResponseException(response()->json([
                'data'   => null,
                'meta'   => (object) [],
                'errors' => [['code' => 'booking_item_not_completed', 'message' => __('reviews::reviews.errors.booking_item_not_completed')]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $existing = $this->repository->findByBookingItemId($context['booking_item_id']);

        if ($existing !== null) {
            throw new HttpResponseException(response()->json([
                'data'   => null,
                'meta'   => (object) [],
                'errors' => [[
                    'code'                      => 'review_already_exists',
                    'message'                   => __('reviews::reviews.errors.review_already_exists'),
                    'existing_review_public_id' => $existing->public_id,
                ]],
            ], Response::HTTP_CONFLICT));
        }

        $review = DB::transaction(fn () => $this->repository->create($data, $context['service_id'], $context['booking_item_id']));

        DB::afterCommit(fn () => event(new ReviewSubmitted(
            reviewId: $review->id,
            reviewPublicId: $review->public_id,
            reviewType: 'service',
            subjectId: $context['service_id'],
            userId: $data->userId,
            rating: $data->rating,
            submittedAt: $review->created_at,
        )));

        return $review;
    }
}
