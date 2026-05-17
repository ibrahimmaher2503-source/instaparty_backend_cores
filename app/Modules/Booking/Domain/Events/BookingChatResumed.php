<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BookingChatResumed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $chatThreadId,
        public readonly string $firestoreThreadId,
        public readonly int $adminId,
        public readonly string $reason,
    ) {}
}
