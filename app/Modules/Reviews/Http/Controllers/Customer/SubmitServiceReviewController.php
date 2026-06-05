<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Customer;

use App\Modules\Reviews\Application\Actions\SubmitServiceReviewAction;
use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Http\Requests\SubmitServiceReviewRequest;
use App\Modules\Reviews\Http\Resources\ServiceReviewResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer - Reviews
 */
class SubmitServiceReviewController
{
    public function __construct(private readonly SubmitServiceReviewAction $action) {}

    public function store(SubmitServiceReviewRequest $request, string $bookingItemPublicId): JsonResponse
    {
        $review = $this->action->execute(new SubmitReviewData(
            bookingSubjectPublicId: $bookingItemPublicId,
            userId: $request->user()->id,
            rating: (int) $request->validated('rating'),
            body: $request->validated('body'),
            locale: str_starts_with($request->header('Accept-Language', 'en'), 'ar') ? 'ar' : 'en',
        ));

        return (new ServiceReviewResource($review))->response()->setStatusCode(201);
    }
}
