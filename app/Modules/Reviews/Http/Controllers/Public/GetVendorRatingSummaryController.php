<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Public;

use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Http\Resources\RatingSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @group Public - Reviews
 */
class GetVendorRatingSummaryController
{
    public function __construct(private readonly VendorReviewRepository $repo) {}

    public function show(string $vendorPublicId): JsonResponse
    {
        $vendorProfileId = DB::table('vendor_profiles')->where('public_id', $vendorPublicId)->value('id');

        if ($vendorProfileId === null) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'not_found']]], 404);
        }

        $aggregate = $this->repo->aggregateApprovedForVendor((int) $vendorProfileId);

        return (new RatingSummaryResource($aggregate))->response();
    }
}
