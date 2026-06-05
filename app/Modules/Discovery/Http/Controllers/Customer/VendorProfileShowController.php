<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Customer;

use App\Modules\Discovery\Application\Services\VendorAvailabilityService;
use App\Modules\Discovery\Http\Resources\ServiceSearchResultResource;
use App\Modules\Discovery\Http\Resources\VendorProfileResource;
use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Composite vendor profile (audit 8.1 / file 17a): the base public profile
 * plus stats, coverage summary, today's hours, featured review, top
 * services, and a portfolio preview — one round-trip for the vendor page
 * header. Cached 5 minutes per (vendor, locale); TTL-based bust in Phase 1.
 *
 * @group Customer - Vendor Browsing
 */
class VendorProfileShowController
{
    public function __invoke(
        Request $request,
        string $publicId,
        VendorBrowsingRepository $repository,
        VendorAvailabilityService $availability,
    ): JsonResponse {
        $vendor = VendorProfile::with(['primaryCity', 'approvedTypes', 'user'])
            ->withCount(['services' => fn ($q) => $q->whereNull('deleted_at')])
            ->whereState('approval_status', ApprovedState::class)
            ->where('public_id', $publicId)
            ->first();

        if ($vendor === null) {
            return ApiResponse::error('Vendor not found.', 404);
        }

        $payload = Cache::remember(
            "customer:vendor-profile:{$vendor->id}:".app()->getLocale(),
            now()->addMinutes(5),
            function () use ($vendor, $request, $repository, $availability): array {
                $stats = $repository->statsFor($vendor->id);
                $coverage = $repository->coverageFor($vendor->id);
                $portfolio = $repository->portfolioFor($vendor->id, 4);
                $featured = $repository->featuredReviewFor($vendor->id);
                $snapshot = $availability->snapshotFor($vendor->id);

                return array_merge(
                    (new VendorProfileResource($vendor))->toArray($request),
                    [
                        'stats' => array_merge($stats, [
                            'response_rate' => null, // no per-vendor response-rate aggregate in Phase 1
                            'avg_response_hours' => $vendor->response_time_avg_minutes !== null
                                ? round(((int) $vendor->response_time_avg_minutes) / 60, 1)
                                : null,
                        ]),
                        'coverage_summary' => ['cities_count' => $coverage->count()],
                        'today_hours' => [
                            'is_open_now' => $snapshot['is_open_now'],
                            'closes_at' => $snapshot['closes_at'],
                            'next_open_at' => $snapshot['next_open_at'],
                        ],
                        'featured_review' => $featured !== null ? [
                            'rating' => (int) $featured->rating,
                            'body' => $featured->body,
                            'reviewer_first_name' => preg_split('/\s+/', trim((string) $featured->reviewer_name))[0] ?? null,
                            'created_at' => $featured->created_at,
                        ] : null,
                        'top_services' => ServiceSearchResultResource::collection(
                            $repository->servicesFor($vendor->id, null, 4)->items()
                        )->toArray($request),
                        'portfolio_preview' => [
                            'total_count' => $portfolio['total_count'],
                            'thumbnails' => array_column(array_slice($portfolio['items'], 0, 4), 'url'),
                        ],
                    ],
                );
            },
        );

        return ApiResponse::success($payload);
    }
}
