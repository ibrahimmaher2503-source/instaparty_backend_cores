<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Events\BookingDraftCreated;
use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Support\Facades\DB;

class CreateBookingDraftAction
{
    public function __construct(private readonly BookingRepository $repository) {}

    public function execute(CreateBookingDraftDTO $dto): Booking
    {
        return DB::transaction(function () use ($dto): Booking {
            $booking = $this->repository->create($dto);
            DB::afterCommit(fn () => event(new BookingDraftCreated($booking)));

            return $booking;
        });
    }
}
