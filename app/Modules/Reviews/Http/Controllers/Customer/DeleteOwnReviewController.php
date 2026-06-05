<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Customer;

use App\Modules\Reviews\Application\Actions\DeleteOwnReviewAction;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Reviews
 */
class DeleteOwnReviewController
{
    public function __construct(
        private readonly DeleteOwnReviewAction $action,
        private readonly ServiceReviewRepository $serviceRepo,
        private readonly VendorReviewRepository $vendorRepo,
    ) {}

    public function destroy(Request $request, string $reviewType, string $publicId): JsonResponse
    {
        $userId = $request->user()->id;

        $review = $reviewType === 'vendor'
            ? $this->vendorRepo->findByPublicIdForUser($publicId, $userId)
            : $this->serviceRepo->findByPublicIdForUser($publicId, $userId);

        if ($review === null) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'not_found']]], 404);
        }

        $this->action->execute($review, $userId);

        return response()->json(['data' => null, 'meta' => (object) [], 'errors' => []], 200);
    }
}
