<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Customer;

use App\Modules\Reviews\Application\Actions\SubmitVendorReviewAction;
use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Http\Requests\SubmitVendorReviewRequest;
use App\Modules\Reviews\Http\Resources\VendorReviewResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer - Reviews
 */
class SubmitVendorReviewController
{
    public function __construct(private readonly SubmitVendorReviewAction $action) {}

    public function store(SubmitVendorReviewRequest $request, string $bookingVendorPublicId): JsonResponse
    {
        $review = $this->action->execute(new SubmitReviewData(
            bookingSubjectPublicId: $bookingVendorPublicId,
            userId: $request->user()->id,
            rating: (int) $request->validated('rating'),
            body: $request->validated('body'),
            locale: str_starts_with($request->header('Accept-Language', 'en'), 'ar') ? 'ar' : 'en',
        ));

        return (new VendorReviewResource($review))->response()->setStatusCode(201);
    }
}
