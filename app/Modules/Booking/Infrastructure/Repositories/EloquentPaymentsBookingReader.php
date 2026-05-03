<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Application\DTOs\PaymentBookingItemReadDto;
use App\Modules\Payments\Application\DTOs\PaymentBookingReadDto;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EloquentPaymentsBookingReader implements PaymentsBookingReader
{
    public function findByPublicId(string $ulid): ?PaymentBookingReadDto
    {
        $row = DB::table('bookings')->where('public_id', $ulid)->first();
        if ($row === null) {
            return null;
        }

        return new PaymentBookingReadDto(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            customerId: (int) $row->customer_id,
            lifecycleStatus: (string) $row->lifecycle_status,
            paymentStatus: (string) $row->payment_status,
            totalMinor: (int) $row->total_minor,
            totalCurrency: (string) $row->total_currency,
            paymentHoldExpiresAt: isset($row->payment_hold_expires_at) && $row->payment_hold_expires_at !== null ? Carbon::parse($row->payment_hold_expires_at) : null,
        );
    }

    public function itemsFor(int $bookingId): array
    {
        $rows = DB::table('booking_items')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->where('booking_vendors.booking_id', $bookingId)
            ->select(['booking_items.id', 'booking_vendors.booking_id', 'booking_items.service_id', 'booking_items.product_type', 'booking_items.effective_starts_at', 'booking_items.item_status'])
            ->get();

        return $rows->map(static fn ($row): PaymentBookingItemReadDto => new PaymentBookingItemReadDto(
            id: (int) $row->id,
            bookingId: (int) $row->booking_id,
            serviceId: (int) $row->service_id,
            productType: ProductType::from((string) $row->product_type),
            eventStartsAt: $row->effective_starts_at ? Carbon::parse($row->effective_starts_at) : null,
            itemStatus: (string) $row->item_status,
        ))->all();
    }

    public function staleHoldBookingIds(): array
    {
        return DB::table('bookings')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('payment_hold_expires_at')
            ->where('payment_hold_expires_at', '<', now())
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
