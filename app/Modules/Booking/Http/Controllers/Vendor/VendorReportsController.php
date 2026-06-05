<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Vendor;

use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 15.2–15.4 — bookings / revenue reports + per-service
 * performance, own data only. Mobile parity ruling (C): mobile passes
 * ?granularity=summary for headline cards; web omits it for daily series.
 * Money stays integer minor units.
 *
 * @group Vendor - Reports
 */
class VendorReportsController
{
    public function bookings(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $vendorId = $this->vendorProfileId($request);

        $base = DB::table('booking_vendors')
            ->where('vendor_profile_id', $vendorId)
            ->whereBetween('created_at', [$from, $to.' 23:59:59']);

        $summary = [
            'total' => (clone $base)->count(),
            'accepted' => (clone $base)->where('sub_status', 'accepted')->count(),
            'completed' => (clone $base)->where('sub_status', 'completed')->count(),
            'rejected' => (clone $base)->where('sub_status', 'rejected')->count(),
        ];

        if ($request->string('granularity')->toString() === 'summary') {
            return ApiResponse::success(['summary' => $summary, 'from' => $from, 'to' => $to]);
        }

        $series = (clone $base)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get([DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as bookings')])
            ->map(fn (object $r): array => ['day' => $r->day, 'bookings' => (int) $r->bookings])
            ->values();

        return ApiResponse::success(['summary' => $summary, 'series' => $series, 'from' => $from, 'to' => $to]);
    }

    public function revenue(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $vendorId = $this->vendorProfileId($request);

        $base = DB::table('booking_vendors')
            ->where('vendor_profile_id', $vendorId)
            ->where('sub_status', 'completed')
            ->whereBetween('created_at', [$from, $to.' 23:59:59']);

        $summary = [
            'gross_minor' => (int) (clone $base)->sum('subtotal_minor'),
            'payout_minor' => (int) (clone $base)->sum('vendor_payout_minor'),
            'currency' => 'EGP',
        ];

        if ($request->string('granularity')->toString() === 'summary') {
            return ApiResponse::success(['summary' => $summary, 'from' => $from, 'to' => $to]);
        }

        $series = (clone $base)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get([
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(subtotal_minor) as gross_minor'),
                DB::raw('SUM(vendor_payout_minor) as payout_minor'),
            ])
            ->map(fn (object $r): array => [
                'day' => $r->day,
                'gross_minor' => (int) $r->gross_minor,
                'payout_minor' => (int) $r->payout_minor,
            ])
            ->values();

        return ApiResponse::success(['summary' => $summary, 'series' => $series, 'from' => $from, 'to' => $to]);
    }

    public function servicesPerformance(Request $request): JsonResponse
    {
        $vendorId = $this->vendorProfileId($request);

        $rows = DB::table('booking_items as bi')
            ->join('booking_vendors as bv', 'bv.id', '=', 'bi.booking_vendor_id')
            ->join('services as s', 's.id', '=', 'bi.service_id')
            ->where('bv.vendor_profile_id', $vendorId)
            ->groupBy('s.id', 's.public_id', 's.product_type')
            ->orderByDesc(DB::raw('SUM(bi.line_total_minor)'))
            ->limit(50)
            ->get([
                's.public_id',
                's.product_type',
                DB::raw('COUNT(bi.id) as booked_items'),
                DB::raw('SUM(bi.line_total_minor) as gross_minor'),
            ])
            ->map(fn (object $r): array => [
                'service_public_id' => $r->public_id,
                'product_type' => $r->product_type,
                'booked_items' => (int) $r->booked_items,
                'gross_minor' => (int) $r->gross_minor,
                'currency' => 'EGP',
            ])
            ->values();

        return ApiResponse::success($rows);
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'granularity' => ['nullable', 'string', 'in:summary,daily'],
        ]);

        return [
            $validated['from'] ?? now()->subDays(30)->toDateString(),
            $validated['to'] ?? now()->toDateString(),
        ];
    }

    private function vendorProfileId(Request $request): int
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return (int) $vendor->id;
    }
}
