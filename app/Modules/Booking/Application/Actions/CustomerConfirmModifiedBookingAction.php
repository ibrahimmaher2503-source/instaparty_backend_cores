<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\CustomerModificationDecided;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem; // used in applyAdd
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerConfirmModifiedBookingAction
{
    /** Fields a vendor may change on an existing item via a modification. */
    private const ALLOWED_UPDATE_PAYLOAD_KEYS = [
        'unit_price_minor',
        'unit_price_currency',
        'quantity',
        'effective_starts_at',
        'effective_ends_at',
        'customization_data',
    ];

    /** Fields a vendor may supply when adding a new item via a modification. */
    private const ALLOWED_ADD_PAYLOAD_KEYS = [
        'service_id',
        'product_type',
        'unit_price_minor',
        'unit_price_currency',
        'quantity',
        'effective_starts_at',
        'effective_ends_at',
        'customization_data',
    ];
    public function execute(CustomerModificationDecisionDTO $dto): Booking
    {
        $cached = $this->getCachedIdempotencyResponse($dto);
        if ($cached !== null) {
            return $cached;
        }

        return DB::transaction(function () use ($dto): Booking {
            $booking = Booking::query()->where('id', $dto->bookingId)->lockForUpdate()->firstOrFail();

            abort_if(
                $booking->customer_id !== $dto->customerId,
                Response::HTTP_FORBIDDEN
            );

            /** @var BookingModification|null $modification */
            $modification = BookingModification::with(['items', 'bookingVendor'])
                ->where('public_id', $dto->modificationPublicId)
                ->whereHas('bookingVendor', fn ($q) => $q->where('booking_id', $booking->id))
                ->lockForUpdate()
                ->first();

            abort_if($modification === null, Response::HTTP_NOT_FOUND);

            abort_if(
                $modification->status !== ModificationStatus::Pending,
                Response::HTTP_CONFLICT,
                'Modification is not in pending status'
            );

            /** @var BookingVendor $bookingVendor */
            $bookingVendor = $modification->bookingVendor;

            if ($dto->decision === 'accepted') {
                $this->applyModificationItems($modification);

                $modification->update([
                    'status' => ModificationStatus::CustomerAccepted,
                    'customer_decision_at' => now(),
                ]);

                $this->recalculateVendorSubtotal($bookingVendor);

                // Advance this vendor's sub_status to accepted
                $bookingVendor->update([
                    'sub_status' => VendorSubStatus::Accepted,
                    'responded_at' => now(),
                ]);

                BookingStateTransition::create([
                    'transitionable_type' => BookingVendor::class,
                    'transitionable_id' => $bookingVendor->id,
                    'from_state' => VendorSubStatus::Modified->value,
                    'to_state' => VendorSubStatus::Accepted->value,
                    'trigger_kind' => 'customer',
                    'triggered_by' => $dto->customerId,
                ]);

                $allAccepted = ! BookingVendor::where('booking_id', $booking->id)
                    ->where('sub_status', '!=', VendorSubStatus::Accepted->value)
                    ->exists();

                if ($allAccepted) {
                    $booking->update([
                        'lifecycle_status' => LifecycleStatus::Confirmed,
                        'confirmed_at' => now(),
                    ]);

                    BookingStateTransition::create([
                        'transitionable_type' => Booking::class,
                        'transitionable_id' => $booking->id,
                        'from_state' => LifecycleStatus::CustomerReview->value,
                        'to_state' => LifecycleStatus::Confirmed->value,
                        'trigger_kind' => 'system',
                    ]);
                } else {
                    $booking->update(['lifecycle_status' => LifecycleStatus::VendorReview]);

                    BookingStateTransition::create([
                        'transitionable_type' => Booking::class,
                        'transitionable_id' => $booking->id,
                        'from_state' => LifecycleStatus::CustomerReview->value,
                        'to_state' => LifecycleStatus::VendorReview->value,
                        'trigger_kind' => 'system',
                    ]);
                }
            } else {
                $modification->update([
                    'status' => ModificationStatus::CustomerRejected,
                    'customer_decision_at' => now(),
                ]);

                $bookingVendor->update(['sub_status' => VendorSubStatus::Pending]);

                BookingStateTransition::create([
                    'transitionable_type' => BookingVendor::class,
                    'transitionable_id' => $bookingVendor->id,
                    'from_state' => VendorSubStatus::Modified->value,
                    'to_state' => VendorSubStatus::Pending->value,
                    'trigger_kind' => 'customer',
                    'triggered_by' => $dto->customerId,
                ]);

                $booking->update(['lifecycle_status' => LifecycleStatus::VendorReview]);

                BookingStateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => LifecycleStatus::CustomerReview->value,
                    'to_state' => LifecycleStatus::VendorReview->value,
                    'trigger_kind' => 'system',
                ]);
            }

            $booking->refresh();
            $bookingConfirmed = $booking->lifecycle_status === LifecycleStatus::Confirmed;

            DB::afterCommit(function () use ($modification, $dto, $booking, $bookingConfirmed): void {
                event(new CustomerModificationDecided($modification, $dto->decision));
                if ($bookingConfirmed) {
                    event(new BookingConfirmed($booking));
                }
                $this->storeIdempotencyResponse($dto, $booking);
            });

            return $booking;
        });
    }

    private function applyModificationItems(BookingModification $modification): void
    {
        foreach ($modification->items as $modItem) {
            /** @var BookingModificationItem $modItem */
            match ($modItem->change_kind) {
                ModificationChangeKind::Update => $this->applyUpdate($modItem),
                ModificationChangeKind::Add => $this->applyAdd($modItem, $modification->booking_vendor_id),
                ModificationChangeKind::Remove => $this->applyRemove($modItem),
            };
        }
    }

    private function applyUpdate(BookingModificationItem $modItem): void
    {
        if ($modItem->target_booking_item_id === null) {
            return;
        }

        $item = BookingItem::find($modItem->target_booking_item_id);
        if ($item !== null) {
            $safePayload = array_intersect_key(
                $modItem->payload,
                array_flip(self::ALLOWED_UPDATE_PAYLOAD_KEYS)
            );
            $item->update($safePayload);
            $item->update(['line_total_minor' => $item->unit_price_minor * $item->quantity]);
        }
    }

    private function applyAdd(BookingModificationItem $modItem, int $bookingVendorId): void
    {
        $safePayload = array_intersect_key(
            $modItem->payload,
            array_flip(self::ALLOWED_ADD_PAYLOAD_KEYS)
        );
        BookingItem::create(array_merge(
            ['public_id' => (string) Str::ulid(), 'booking_vendor_id' => $bookingVendorId],
            $safePayload
        ));
    }

    private function applyRemove(BookingModificationItem $modItem): void
    {
        if ($modItem->target_booking_item_id !== null) {
            BookingItem::where('id', $modItem->target_booking_item_id)->delete();
        }
    }

    private function recalculateVendorSubtotal(BookingVendor $bookingVendor): void
    {
        $subtotal = BookingItem::where('booking_vendor_id', $bookingVendor->id)->sum('line_total_minor');
        $bookingVendor->update(['subtotal_minor' => $subtotal]);
    }

    private function requestHash(CustomerModificationDecisionDTO $dto): string
    {
        return hash('sha256', 'bookings.modifications.decide|'.$dto->customerId.'|'.$dto->modificationPublicId.'|'.$dto->decision);
    }

    private function getCachedIdempotencyResponse(CustomerModificationDecisionDTO $dto): ?Booking
    {
        $row = DB::table('idempotency_keys')
            ->where('key', $dto->idempotencyKey)
            ->where('user_id', $dto->customerId)
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

        return Booking::find((int) ($body['booking_id'] ?? 0));
    }

    private function storeIdempotencyResponse(CustomerModificationDecisionDTO $dto, Booking $booking): void
    {
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $dto->idempotencyKey,
            'user_id' => $dto->customerId,
            'route' => 'bookings.modifications.decide',
            'request_hash' => $this->requestHash($dto),
            'response_status' => Response::HTTP_OK,
            'response_body' => json_encode(['booking_id' => $booking->id]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);
    }
}
