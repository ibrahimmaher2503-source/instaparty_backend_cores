<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Customer;

use App\Modules\Reviews\Application\Actions\UpdateOwnReviewAction;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Http\Resources\MyReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateOwnReviewController
{
    public function __construct(
        private readonly ServiceReviewRepository $serviceRepo,
        private readonly VendorReviewRepository $vendorRepo,
        private readonly UpdateOwnReviewAction $action,
    ) {}

    public function update(Request $request, string $reviewType, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $reviewType === 'vendor'
            ? $this->vendorRepo->findByPublicIdForUser($publicId, (int) $request->user()->id)
            : $this->serviceRepo->findByPublicIdForUser($publicId, (int) $request->user()->id);

        if ($review === null) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'not_found']]], 404);
        }

        $updated = $this->action->execute($review, (int) $request->user()->id, $validated['rating'] ?? null, $validated['body'] ?? null);

        return response()->json(['data' => new MyReviewResource($updated), 'meta' => (object) [], 'errors' => []], 200);
    }
}
