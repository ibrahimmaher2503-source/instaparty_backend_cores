<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\SubmitBookingDTO;
use App\Modules\Booking\Domain\Contracts\TaxRateResolver;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SubmitBookingAction
{
    public function __construct(
        private readonly TaxRateResolver $taxRateResolver,
        private readonly EnforceCoverageAreaMinimumOrderAction $enforceMinimumOrder,
    ) {}

    public function execute(SubmitBookingDTO $dto): Booking
    {
        $cached = $this->getCachedIdempotencyResponse($dto);
        if ($cached !== null) {
            return $cached;
        }

        return DB::transaction(function () use ($dto): Booking {
            $booking = Booking::where('id', $dto->bookingId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $booking->customer_id !== $dto->customerId,
                Response::HTTP_FORBIDDEN
            );

            abort_if(
                ! ($booking->lifecycle_status instanceof DraftState),
                Response::HTTP_CONFLICT,
                'Booking is not in draft status'
            );

            $hasItems = DB::table('booking_items')
                ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
                ->where('booking_vendors.booking_id', $booking->id)
                ->exists();

            abort_if(! $hasItems, Response::HTTP_UNPROCESSABLE_ENTITY, 'Booking has no items');

            $this->enforceMinimumOrder->execute($booking->id);

            // Resolve and snapshot VAT per booking item
            $totalVatMinor = 0;
            $items = DB::table('booking_items')
                ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
                ->where('booking_vendors.booking_id', $booking->id)
                ->select('booking_items.id', 'booking_items.product_type', 'booking_items.unit_price_minor', 'booking_items.quantity')
                ->get();

            foreach ($items as $item) {
                $rateBps = $this->taxRateResolver->resolveRateBpsForProductType($item->product_type);
                $lineTotal = $item->unit_price_minor * $item->quantity;
                $vatAmount = (int) round($lineTotal * $rateBps / 10000);
                DB::table('booking_items')->where('id', $item->id)->update([
                    'vat_rate_bps' => $rateBps,
                    'vat_amount_minor' => $vatAmount,
                ]);
                $totalVatMinor += $vatAmount;
            }

            $booking->update([
                'lifecycle_status' => VendorReviewState::class,
                'submitted_at' => now(),
                'total_vat_minor' => $totalVatMinor,
            ]);

            StateTransition::create([
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'from_state' => LifecycleStatus::Draft->value,
                'to_state' => LifecycleStatus::VendorReview->value,
                'trigger_kind' => 'customer',
                'triggered_by' => $dto->customerId,
            ]);

            $deadline = now()->addHours(24);
            $vendorEvents = [];

            /** @var BookingVendor $bookingVendor */
            foreach ($booking->vendors as $bookingVendor) {
                $bookingVendor->update([
                    'sub_status' => VendorSubStatus::Pending,
                    'response_deadline' => $deadline,
                ]);
                $vendorEvents[] = new BookingSubmittedToVendor($bookingVendor, $booking);
            }

            $booking->load('vendors');

            DB::afterCommit(function () use ($vendorEvents, $dto, $booking): void {
                foreach ($vendorEvents as $event) {
                    event($event);
                }
                $this->storeIdempotencyResponse($dto, $booking);
            });

            return $booking;
        });
    }

    private function requestHash(SubmitBookingDTO $dto): string
    {
        // Canonical pattern (Shared\IdempotencyService:30): route|content —
        // plus customerId/bookingId, which here stand in for the route params.
        return hash('sha256', 'bookings.submit|'.$dto->customerId.'|'.$dto->bookingId.'|'.$dto->requestContent);
    }

    private function getCachedIdempotencyResponse(SubmitBookingDTO $dto): ?Booking
    {
        $row = DB::table('idempotency_keys')
            ->where('key', $dto->idempotencyKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        if (! hash_equals($row->request_hash, $this->requestHash($dto))) {
            abort(Response::HTTP_CONFLICT, 'Idempotency key conflict');
        }

        /** @var array<string,mixed> $body */
        $body = json_decode($row->response_body, true) ?? [];
        $bookingId = (int) ($body['booking_id'] ?? 0);

        return Booking::with('vendors')->find($bookingId);
    }

    private function storeIdempotencyResponse(SubmitBookingDTO $dto, Booking $booking): void
    {
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $dto->idempotencyKey,
            'user_id' => $dto->customerId,
            'route' => 'bookings.submit',
            'request_hash' => $this->requestHash($dto),
            'response_status' => Response::HTTP_OK,
            'response_body' => json_encode(['booking_id' => $booking->id]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);
    }
}
