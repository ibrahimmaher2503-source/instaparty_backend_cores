<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Application\Services;

use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vendor opening-hours read model (audit 8.7 / 8.8). Weekly hours only in
 * Phase 1 — there is no vendor-holiday table; blocked_dates is therefore
 * always empty and documented as such in the contract.
 *
 * day_of_week convention: 0 = Sunday … 6 = Saturday (matches Carbon).
 */
class VendorAvailabilityService
{
    public function __construct(private readonly VendorBrowsingRepository $repository) {}

    /** @return array<string, mixed> */
    public function snapshotFor(int $vendorProfileId, string $timezone = 'Africa/Cairo'): array
    {
        $rows = $this->repository->weeklyHoursFor($vendorProfileId);
        $now = CarbonImmutable::now($timezone);

        $today = $this->rowFor($rows, $now->dayOfWeek);
        $isOpenNow = $today !== null && $this->isWithin($now, $today);

        return [
            'is_open_now' => $isOpenNow,
            'closes_at' => $isOpenNow ? substr((string) $today->closes_at, 0, 5) : null,
            'next_open_at' => $isOpenNow ? null : $this->nextOpenAt($rows, $now)?->toIso8601String(),
            'weekly_hours' => $rows->map(fn (object $r): array => [
                'day_of_week' => (int) $r->day_of_week,
                'opens_at' => $r->opens_at !== null ? substr((string) $r->opens_at, 0, 5) : null,
                'closes_at' => $r->closes_at !== null ? substr((string) $r->closes_at, 0, 5) : null,
            ])->values()->all(),
            // vendor_blocked_dates landed 2026-06-05 (vendor-portal 8.3–8.5).
            'blocked_dates' => $this->blockedDatesFor($vendorProfileId),
        ];
    }

    /** @return array<string, mixed> */
    public function checkDate(int $vendorProfileId, CarbonImmutable $date): array
    {
        if (in_array($date->toDateString(), $this->blockedDatesFor($vendorProfileId), true)) {
            return [
                'date' => $date->toDateString(),
                'is_available' => false,
                'opens_at' => null,
                'closes_at' => null,
            ];
        }

        $row = $this->rowFor($this->repository->weeklyHoursFor($vendorProfileId), $date->dayOfWeek);
        $open = $row !== null && $row->opens_at !== null;

        return [
            'date' => $date->toDateString(),
            'is_available' => $open,
            'opens_at' => $open ? substr((string) $row->opens_at, 0, 5) : null,
            'closes_at' => $open ? substr((string) $row->closes_at, 0, 5) : null,
        ];
    }

    /** @return list<string> upcoming blocked dates (Y-m-d) */
    private function blockedDatesFor(int $vendorProfileId): array
    {
        return DB::table('vendor_blocked_dates')
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('blocked_date', '>=', now()->toDateString())
            ->orderBy('blocked_date')
            ->pluck('blocked_date')
            ->map(fn ($d): string => substr((string) $d, 0, 10))
            ->all();
    }

    private function rowFor(Collection $rows, int $dayOfWeek): ?object
    {
        return $rows->firstWhere('day_of_week', $dayOfWeek);
    }

    private function isWithin(CarbonImmutable $now, object $row): bool
    {
        if ($row->opens_at === null || $row->closes_at === null) {
            return false;
        }

        $time = $now->format('H:i:s');

        return $time >= (string) $row->opens_at && $time < (string) $row->closes_at;
    }

    private function nextOpenAt(Collection $rows, CarbonImmutable $now): ?CarbonImmutable
    {
        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $now->addDays($offset);
            $row = $this->rowFor($rows, $day->dayOfWeek);

            if ($row === null || $row->opens_at === null) {
                continue;
            }

            $opens = $day->setTimeFromTimeString((string) $row->opens_at);
            if ($opens->isAfter($now)) {
                return $opens;
            }
        }

        return null;
    }
}
