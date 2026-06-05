<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Public;

use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Http\Resources\RatingSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @group Public - Reviews
 */
class GetServiceRatingSummaryController
{
    public function __construct(private readonly ServiceReviewRepository $repo) {}

    public function show(string $servicePublicId): JsonResponse
    {
        $serviceId = DB::table('services')->where('public_id', $servicePublicId)->value('id');

        if ($serviceId === null) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'not_found']]], 404);
        }

        $aggregate = $this->repo->aggregateApprovedForService((int) $serviceId);

        return (new RatingSummaryResource($aggregate))->response();
    }
}
