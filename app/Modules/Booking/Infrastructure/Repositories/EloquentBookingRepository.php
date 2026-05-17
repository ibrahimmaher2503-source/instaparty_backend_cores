<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use Illuminate\Support\Str;

class EloquentBookingRepository implements BookingRepository
{
    public function create(CreateBookingDraftDTO $dto): Booking
    {
        $booking = Booking::create([
            'public_id' => (string) Str::ulid(),
            'customer_id' => $dto->customerId,
            'occasion_id' => $dto->occasionId,
            'lifecycle_status' => DraftState::class,
            'payment_status' => UnpaidState::class,
            'fulfillment_status' => FulfillmentStatus::NotStarted,
            'event_starts_at' => $dto->eventStartsAt,
            'event_ends_at' => $dto->eventEndsAt,
            'guest_count' => $dto->guestCount,
            'theme' => $dto->theme,
            'celebrant_name' => $dto->celebrantName,
            'celebrant_dob' => $dto->celebrantDob,
            'celebrant_gender' => $dto->celebrantGender,
            'subtotal_currency' => 'EGP',
            'delivery_total_currency' => 'EGP',
            'discount_total_currency' => 'EGP',
            'loyalty_redeemed_currency' => 'EGP',
            'total_currency' => 'EGP',
            'amount_paid_currency' => 'EGP',
        ]);

        $booking->reference_no = 'IP-'.date('Y').'-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT);
        $booking->save();

        BookingAddress::create([
            'booking_id' => $booking->id,
            'city_id' => $dto->addressCityId,
            'address_line' => $dto->addressLine,
            'building' => $dto->addressBuilding,
            'floor' => $dto->addressFloor,
            'apartment' => $dto->addressApartment,
            'landmark' => $dto->addressLandmark,
            'latitude' => $dto->addressLatitude,
            'longitude' => $dto->addressLongitude,
            'recipient_name' => $dto->recipientName,
            'recipient_phone_e164' => $dto->recipientPhoneE164,
        ]);

        return $booking->load('address');
    }

    public function findDraftForCustomer(int $bookingId, int $customerId): ?Booking
    {
        return Booking::where('id', $bookingId)
            ->where('customer_id', $customerId)
            ->whereState('lifecycle_status', DraftState::class)
            ->first();
    }

    public function findByPublicId(string $publicId): ?Booking
    {
        return Booking::where('public_id', $publicId)->first();
    }
}
