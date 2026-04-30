<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingDraftCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Booking $booking) {}
}
