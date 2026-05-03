<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Public;

use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Http\Resources\PublicVendorReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListVendorReviewsController
{
    public function __construct(private readonly VendorReviewRepository $repo) {}

    public function index(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendorProfileId = DB::table('vendor_profiles')
            ->where('public_id', $vendorPublicId)
            ->value('id');

        if ($vendorProfileId === null) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'not_found']]], 404);
        }

        $result = $this->repo->listApprovedForVendor((int) $vendorProfileId, [
            'cursor' => $request->query('cursor'),
            'limit'  => 15,
        ]);

        return response()->json([
            'data' => PublicVendorReviewResource::collection($result['items']),
            'meta' => ['next_cursor' => $result['next_cursor']],
        ]);
    }
}
