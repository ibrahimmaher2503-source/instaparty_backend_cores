<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Vendor;

use App\Modules\Reviews\Application\Actions\RespondToReviewAction;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Reviews\Http\Requests\RespondToReviewRequest;
use App\Modules\Reviews\Http\Resources\ReviewResponseResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @response 201 {"data":{"id":"...","body":"Thank you!","locale":"en","moderation_status":"pending"},"meta":{},"errors":null}
 * @response 404 {"data":null,"meta":{},"errors":{"message":"review_not_found"}}
 * @response 403 {"data":null,"meta":{},"errors":{"message":"This action is unauthorized."}}
 * @response 401 {"message":"Unauthenticated."}
 */
class RespondToReviewController
{
    public function __construct(private readonly RespondToReviewAction $action) {}

    public function store(RespondToReviewRequest $request, string $reviewType, string $publicId): JsonResponse
    {
        $type = ReviewType::from($reviewType);
        $vendor = $request->user()->vendorProfile;

        $review = match ($type) {
            ReviewType::Service => ServiceReview::where('public_id', $publicId)
                ->where('service_id', function ($query) use ($vendor): void {
                    $query->select('id')->from('services')->where('vendor_profile_id', $vendor->id);
                })
                ->first(),
            ReviewType::Vendor => VendorReview::where('public_id', $publicId)
                ->where('vendor_profile_id', $vendor->id)
                ->first(),
        };

        if ($review === null) {
            throw new NotFoundHttpException('review_not_found');
        }

        $response = $this->action->execute(
            reviewId: $review->id,
            reviewType: $type,
            vendorProfile: $vendor,
            body: $request->validated('body'),
            locale: str_starts_with($request->header('Accept-Language', 'en'), 'ar') ? 'ar' : 'en',
        );

        return (new ReviewResponseResource($response))->response()->setStatusCode(201);
    }
}
