<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Actions;

use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Domain\Contracts\BookingVendorReviewabilityReader;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Domain\Events\ReviewSubmitted;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SubmitVendorReviewAction
{
    public function __construct(
        private readonly BookingVendorReviewabilityReader $eligibilityReader,
        private readonly VendorReviewRepository $repository,
    ) {}

    public function execute(SubmitReviewData $data): VendorReview
    {
        $context = $this->eligibilityReader->resolveReviewableContext($data->bookingSubjectPublicId, $data->userId);

        if ($context === null) {
            throw new HttpResponseException(response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [['code' => 'booking_vendor_items_not_all_completed', 'message' => __('reviews::reviews.errors.booking_vendor_items_not_all_completed')]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $existing = $this->repository->findByBookingVendorId($context['booking_vendor_id']);

        if ($existing !== null) {
            throw new HttpResponseException(response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [[
                    'code' => 'review_already_exists',
                    'message' => __('reviews::reviews.errors.review_already_exists'),
                    'existing_review_public_id' => $existing->public_id,
                ]],
            ], Response::HTTP_CONFLICT));
        }

        $review = DB::transaction(fn () => $this->repository->create($data, $context['vendor_profile_id'], $context['booking_vendor_id']));

        DB::afterCommit(fn () => event(new ReviewSubmitted(
            reviewId: $review->id,
            reviewPublicId: $review->public_id,
            reviewType: 'vendor',
            subjectId: $context['vendor_profile_id'],
            userId: $data->userId,
            rating: $data->rating,
            submittedAt: $review->created_at,
        )));

        return $review;
    }
}
