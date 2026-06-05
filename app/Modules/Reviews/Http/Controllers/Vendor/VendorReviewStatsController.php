<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Vendor;

use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 14.4 — aggregate review stats: vendor-level rating plus the
 * per-service breakdown (approved reviews only, own services only).
 */
class VendorReviewStatsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $perService = DB::table('services as s')
            ->leftJoin('service_reviews as r', function ($join): void {
                $join->on('r.service_id', '=', 's.id')->where('r.moderation_status', 'approved');
            })
            ->where('s.vendor_profile_id', $vendor->id)
            ->whereNull('s.deleted_at')
            ->groupBy('s.id', 's.public_id', 's.product_type')
            ->get([
                's.public_id',
                's.product_type',
                DB::raw('COUNT(r.id) as reviews_count'),
                DB::raw('AVG(r.rating) as rating_avg'),
            ])
            ->map(fn (object $row): array => [
                'service_public_id' => $row->public_id,
                'product_type' => $row->product_type,
                'reviews_count' => (int) $row->reviews_count,
                'rating_avg' => $row->rating_avg !== null ? round((float) $row->rating_avg, 2) : null,
            ])
            ->values();

        return ApiResponse::success([
            'vendor' => [
                'rating_avg' => $vendor->rating_avg !== null ? (float) $vendor->rating_avg : null,
                'rating_count' => (int) ($vendor->rating_count ?? 0),
            ],
            'services' => $perService,
        ]);
    }
}
