<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Domain\Models\Booking;

interface BookingRepository
{
    public function create(CreateBookingDraftDTO $dto): Booking;

    public function findDraftForCustomer(int $bookingId, int $customerId): ?Booking;

    public function findByPublicId(string $publicId): ?Booking;
}
