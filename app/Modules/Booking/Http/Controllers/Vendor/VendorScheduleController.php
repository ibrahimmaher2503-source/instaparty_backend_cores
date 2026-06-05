<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Vendor;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Http\Resources\BookingItemResource;
use App\Modules\Shared\Http\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/v1/vendor/schedule (G7) — the vendor's operational view:
 * booking items of accepted/in-progress bookings whose event falls in the
 * requested window (today | tomorrow | week).
 *
 * @group Vendor - Schedule
 */
class VendorScheduleController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'window' => ['nullable', Rule::in(['today', 'tomorrow', 'week'])],
            // Vendor-portal 7.3 mobile parity (C): explicit calendar range
            // (max 62 days) overrides window. The 62-day cap is enforced
            // below — 'before_or_equal' takes a date/field, not field-relative
            // arithmetic, so it cannot express "from + 62 days".
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
        ]);

        if ($request->filled('from') && $request->filled('to')) {
            $rangeFrom = Carbon::parse((string) $request->query('from'));
            if ($rangeFrom->diffInDays(Carbon::parse((string) $request->query('to'))) > 62) {
                throw ValidationException::withMessages([
                    'to' => __('booking::booking.errors.schedule_range_too_wide.message'),
                ]);
            }
        }

        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $tz = $request->user()->timezone ?? 'Africa/Cairo';
        $window = (string) $request->query('window', 'today');

        [$from, $to] = $request->filled('from')
            ? [
                Carbon::parse((string) $request->query('from'), $tz)->startOfDay(),
                Carbon::parse((string) $request->query('to'), $tz)->endOfDay(),
            ]
            : match ($window) {
                'tomorrow' => [now($tz)->addDay()->startOfDay(), now($tz)->addDay()->endOfDay()],
                'week' => [now($tz)->startOfDay(), now($tz)->addDays(7)->endOfDay()],
                default => [now($tz)->startOfDay(), now($tz)->endOfDay()],
            };

        $items = BookingItem::query()
            ->whereHas('bookingVendor', fn ($q) => $q
                ->where('vendor_profile_id', $vendor->id)
                ->whereIn('sub_status', [VendorSubStatus::Accepted->value, VendorSubStatus::InProgress->value])
                ->whereHas('booking', fn ($b) => $b->whereBetween('event_starts_at', [$from->utc(), $to->utc()])))
            ->with(['bookingVendor.booking'])
            ->get()
            ->sortBy(fn (BookingItem $item) => $item->bookingVendor->booking->event_starts_at)
            ->values();

        return ApiResponse::success(
            $items
                ->groupBy(fn (BookingItem $item) => $item->bookingVendor->booking->event_starts_at
                    ->setTimezone($tz)
                    ->toDateString())
                ->map(fn ($group, string $date): array => [
                    'date' => $date,
                    'items' => BookingItemResource::collection($group->values()),
                ])
                ->values(),
            ['window' => $window],
        );
    }
}
