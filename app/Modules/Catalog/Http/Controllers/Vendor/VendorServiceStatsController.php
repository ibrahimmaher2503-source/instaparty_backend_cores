<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 4.16 — per-service performance: views (analytics_events
 * service_view sink), booked counts, and conversion. Own services only.
 */
class VendorServiceStatsController
{
    public function __invoke(Request $request, string $publicId): JsonResponse
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $service = Service::query()
            ->where('vendor_profile_id', $vendor->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $views = (int) DB::table('analytics_events')
            ->where('event_type', 'service_view')
            ->whereJsonContains('payload->service_id', $service->id)
            ->count();

        $bookedItems = (int) DB::table('booking_items')
            ->where('service_id', $service->id)
            ->count();

        return ApiResponse::success([
            'public_id' => $service->public_id,
            'views' => $views,
            'booked_items' => $bookedItems,
            'conversion_rate' => $views > 0 ? round($bookedItems / $views, 4) : null,
            'rating_avg' => $service->rating_avg !== null ? (float) $service->rating_avg : null,
            'rating_count' => (int) ($service->rating_count ?? 0),
        ]);
    }
}
