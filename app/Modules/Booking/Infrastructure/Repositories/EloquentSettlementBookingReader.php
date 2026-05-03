<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use Illuminate\Support\Facades\DB;

class EloquentSettlementBookingReader implements SettlementBookingReader
{
    public function findItemById(int $id): ?BookingItemSnapshotDto
    {
        $row = DB::table('booking_items')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->where('booking_items.id', $id)
            ->select([
                'booking_items.id',
                'booking_items.public_id',
                'booking_vendors.booking_id',
                'booking_vendors.vendor_profile_id',
                'booking_items.product_type',
                'booking_items.line_total_minor',
                'booking_items.line_total_currency',
                'booking_items.commission_bps',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->rowToDto($row);
    }

    /**
     * @return BookingItemSnapshotDto[]
     */
    public function itemsForPayment(int $bookingId): array
    {
        $rows = DB::table('booking_items')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->where('booking_vendors.booking_id', $bookingId)
            ->select([
                'booking_items.id',
                'booking_items.public_id',
                'booking_vendors.booking_id',
                'booking_vendors.vendor_profile_id',
                'booking_items.product_type',
                'booking_items.line_total_minor',
                'booking_items.line_total_currency',
                'booking_items.commission_bps',
            ])
            ->get();

        return $rows->map(fn (object $row): BookingItemSnapshotDto => $this->rowToDto($row))->all();
    }

    private function rowToDto(object $row): BookingItemSnapshotDto
    {
        return new BookingItemSnapshotDto(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            bookingId: (int) $row->booking_id,
            vendorProfileId: (int) $row->vendor_profile_id,
            categoryId: null, // category_id is not stored on booking_items; resolved at booking time
            productType: ProductType::from((string) $row->product_type),
            totalMinor: (int) $row->line_total_minor,
            totalCurrency: (string) $row->line_total_currency,
            commissionBps: isset($row->commission_bps) ? (int) $row->commission_bps : null,
        );
    }
}
