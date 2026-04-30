<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddItemToBookingAction
{
    public function __construct(private readonly CatalogServiceReader $catalogReader) {}

    public function execute(AddBookingItemDTO $dto): BookingItem
    {
        return DB::transaction(function () use ($dto): BookingItem {
            $booking = Booking::find($dto->bookingId);

            if ($booking === null) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Booking not found');
            }
            if ($booking->customer_id !== $dto->customerId) {
                $this->abortWith(Response::HTTP_FORBIDDEN, 'Forbidden');
            }
            if ($booking->lifecycle_status !== LifecycleStatus::Draft) {
                $this->abortWith(Response::HTTP_CONFLICT, 'Booking is not in draft status');
            }

            $service = $this->catalogReader->findPublishedById($dto->serviceId);
            if ($service === null) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Service not found or unavailable');
            }

            $effectiveStart = $dto->effectiveStartsAt ?? $booking->event_starts_at;
            $effectiveEnd = $dto->effectiveEndsAt ?? $booking->event_ends_at;

            $reservationId = match ($service->productType) {
                ProductType::Rental => $this->reserveRental($service, $dto, $effectiveStart, $effectiveEnd),
                ProductType::Sale => $this->reserveSale($service, $dto),
                ProductType::Digital => null,
            };

            $initialStatus = match ($service->productType) {
                ProductType::Rental => 'pending_delivery',
                ProductType::Sale => 'pending',
                ProductType::Digital => 'pending',
            };

            $vendor = BookingVendor::firstOrCreate(
                ['booking_id' => $booking->id, 'vendor_profile_id' => $service->vendorProfileId],
                [
                    'public_id' => (string) Str::ulid(),
                    'sub_status' => 'pending',
                    'subtotal_currency' => $service->basePriceCurrency,
                    'delivery_fee_currency' => $service->basePriceCurrency,
                    'commission_currency' => $service->basePriceCurrency,
                    'vendor_payout_currency' => $service->basePriceCurrency,
                ]
            );

            $lineTotal = $service->basePriceMinor * $dto->quantity;

            $item = BookingItem::create([
                'public_id' => (string) Str::ulid(),
                'booking_vendor_id' => $vendor->id,
                'service_id' => $service->id,
                'product_type' => $service->productType->value,
                'name_snapshot' => ['en' => $service->nameEn, 'ar' => $service->nameAr],
                'unit_price_minor' => $service->basePriceMinor,
                'unit_price_currency' => $service->basePriceCurrency,
                'line_total_minor' => $lineTotal,
                'line_total_currency' => $service->basePriceCurrency,
                'commission_currency' => $service->basePriceCurrency,
                'quantity' => $dto->quantity,
                'effective_starts_at' => $effectiveStart,
                'effective_ends_at' => $effectiveEnd,
                'customization_data' => $dto->customizationData,
                'item_status' => $initialStatus,
                'commission_bps' => 0,
            ]);

            if ($reservationId !== null) {
                DB::table('service_inventory_reservations')
                    ->where('id', $reservationId)
                    ->update(['booking_item_id' => $item->id]);
            }

            $item->setRelation('bookingVendor', $vendor);
            DB::afterCommit(fn () => event(new BookingItemAdded($item)));

            return $item;
        });
    }

    private function reserveRental(
        ServiceReadDTO $service,
        AddBookingItemDTO $dto,
        Carbon $start,
        Carbon $end,
    ): int {
        DB::table('services')->where('id', $service->id)->lockForUpdate()->first();

        $overlapping = DB::table('service_inventory_reservations')
            ->where('service_id', $service->id)
            ->whereIn('status', [ReservationStatus::Held->value, ReservationStatus::Confirmed->value])
            ->where('reserved_starts_at', '<', $end)
            ->where('reserved_ends_at', '>', $start)
            ->count();

        if ($overlapping > 0) {
            $this->abortWith(Response::HTTP_CONFLICT, 'Service is unavailable for the requested dates');
        }

        return (int) DB::table('service_inventory_reservations')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'service_id' => $service->id,
            'user_id' => $dto->customerId,
            'product_type' => ProductType::Rental->value,
            'hold_type' => HoldType::Cart->value,
            'status' => ReservationStatus::Held->value,
            'reserved_starts_at' => $start,
            'reserved_ends_at' => $end,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(HoldType::Cart->ttlMinutes()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reserveSale(ServiceReadDTO $service, AddBookingItemDTO $dto): int
    {
        DB::table('services')->where('id', $service->id)->lockForUpdate()->first();

        $heldCount = (int) DB::table('service_inventory_reservations')
            ->where('service_id', $service->id)
            ->whereIn('status', [ReservationStatus::Held->value, ReservationStatus::Confirmed->value])
            ->sum('quantity');

        $available = ($service->stockQuantity ?? 0) - $heldCount;

        if ($available < $dto->quantity) {
            $this->abortWith(Response::HTTP_CONFLICT, 'Insufficient stock');
        }

        return (int) DB::table('service_inventory_reservations')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'service_id' => $service->id,
            'user_id' => $dto->customerId,
            'product_type' => ProductType::Sale->value,
            'hold_type' => HoldType::Cart->value,
            'status' => ReservationStatus::Held->value,
            'quantity' => $dto->quantity,
            'expires_at' => now()->addMinutes(HoldType::Cart->ttlMinutes()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function abortWith(int $status, string $message): never
    {
        throw new HttpResponseException(
            response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['message' => $message]], $status)
        );
    }
}
