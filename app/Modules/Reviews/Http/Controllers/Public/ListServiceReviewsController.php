<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Public;

use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Http\Resources\PublicServiceReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Public - Reviews
 */
class ListServiceReviewsController
{
    public function __construct(private readonly ServiceReviewRepository $repo) {}

    public function index(Request $request, string $servicePublicId): JsonResponse
    {
        $serviceId = DB::table('services')
            ->where('public_id', $servicePublicId)
            ->value('id');

        if ($serviceId === null) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'not_found']]], 404);
        }

        $result = $this->repo->listApprovedForService((int) $serviceId, [
            'cursor' => $request->query('cursor'),
            'limit' => 15,
        ]);

        return response()->json([
            'data' => PublicServiceReviewResource::collection($result['items']),
            'meta' => ['next_cursor' => $result['next_cursor']],
        ]);
    }
}
