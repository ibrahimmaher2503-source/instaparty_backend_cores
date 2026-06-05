<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Customer;

use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Reviews\Http\Resources\MyReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Reviews
 */
class ListMyReviewsController
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $serviceReviews = ServiceReview::where('user_id', $userId)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get()
            ->map(fn ($r) => ['review_type' => 'service', 'review' => $r]);

        $vendorReviews = VendorReview::where('user_id', $userId)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get()
            ->map(fn ($r) => ['review_type' => 'vendor', 'review' => $r]);

        $items = $serviceReviews->merge($vendorReviews)
            ->sortByDesc(fn ($item) => $item['review']->created_at)
            ->values()
            ->map(fn ($item) => (new MyReviewResource($item['review']))->additional(['review_type' => $item['review_type']]));

        return response()->json(['data' => $items, 'meta' => (object) [], 'errors' => []]);
    }
}
