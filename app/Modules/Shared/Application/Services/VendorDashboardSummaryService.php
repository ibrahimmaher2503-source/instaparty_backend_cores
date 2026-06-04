<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Support\Facades\Cache;

/**
 * Vendor dashboard summary for the mobile/API surface (G4).
 *
 * Same metric set as VendorStatsOverviewWidget but API-shaped: integer minor
 * units + ISO timestamps, no display formatting (that is the client's job).
 * Cached for 5 minutes per vendor under its own key so widget and API can
 * evolve independently.
 */
class VendorDashboardSummaryService
{
    private const CACHE_TTL_SECONDS = 300;

    /** @return array<string, mixed> */
    public function summarize(VendorProfile $vendorProfile): array
    {
        $vendorId = $vendorProfile->id;

        return Cache::remember(
            "vendor_dashboard_summary_api_{$vendorId}",
            self::CACHE_TTL_SECONDS,
            function () use ($vendorId): array {
                $now = now();
                $som = $now->copy()->startOfMonth();

                $wallet = Wallet::query()
                    ->where('owner_type', VendorProfile::class)
                    ->where('owner_id', $vendorId)
                    ->where('currency', 'EGP')
                    ->first();

                $avgRating = ServiceReview::query()
                    ->whereHas('service', fn ($q) => $q->where('vendor_profile_id', $vendorId))
                    ->where('moderation_status', ModerationStatus::Approved)
                    ->avg('rating');

                return [
                    'action_required' => [
                        'pending_bookings' => BookingVendor::query()
                            ->where('vendor_profile_id', $vendorId)
                            ->where('sub_status', VendorSubStatus::Pending)
                            ->count(),
                        'next_response_deadline' => BookingVendor::query()
                            ->where('vendor_profile_id', $vendorId)
                            ->where('sub_status', VendorSubStatus::Pending)
                            ->whereNotNull('response_deadline')
                            ->where('response_deadline', '>', $now)
                            ->orderBy('response_deadline')
                            ->value('response_deadline')?->toIso8601String(),
                        'pending_services' => Service::query()
                            ->where('vendor_profile_id', $vendorId)
                            ->where('status', ServiceStatus::PendingReview)
                            ->count(),
                    ],
                    'operations' => [
                        'active_bookings' => BookingVendor::query()
                            ->where('vendor_profile_id', $vendorId)
                            ->whereIn('sub_status', [VendorSubStatus::Accepted->value, VendorSubStatus::InProgress->value])
                            ->count(),
                        'upcoming_events_7d' => BookingVendor::query()
                            ->where('vendor_profile_id', $vendorId)
                            ->where('sub_status', VendorSubStatus::Accepted->value)
                            ->whereHas('booking', fn ($q) => $q->whereBetween('event_starts_at', [$now, $now->copy()->addDays(7)]))
                            ->count(),
                    ],
                    'month_to_date' => [
                        'bookings' => BookingVendor::query()
                            ->where('vendor_profile_id', $vendorId)
                            ->whereNotIn('sub_status', [VendorSubStatus::Cancelled->value, VendorSubStatus::TimedOut->value])
                            ->whereBetween('created_at', [$som, $now])
                            ->count(),
                        'revenue_minor' => (int) BookingVendor::query()
                            ->where('vendor_profile_id', $vendorId)
                            ->whereIn('sub_status', [VendorSubStatus::Accepted->value, VendorSubStatus::InProgress->value, VendorSubStatus::Completed->value])
                            ->whereBetween('created_at', [$som, $now])
                            ->sum('vendor_payout_minor'),
                        'currency' => 'EGP',
                    ],
                    'wallet' => [
                        'available_minor' => $wallet ? max(0, (int) $wallet->balance_minor - (int) $wallet->pending_withdrawal_minor) : 0,
                        'pending_withdrawal_minor' => $wallet ? (int) $wallet->pending_withdrawal_minor : 0,
                        'currency' => 'EGP',
                    ],
                    'rating' => [
                        'average' => $avgRating !== null ? round((float) $avgRating, 1) : null,
                    ],
                ];
            },
        );
    }
}
