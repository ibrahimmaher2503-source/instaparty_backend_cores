<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingChatResumed;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use Illuminate\Contracts\Queue\ShouldQueue;

final class OnBookingChatResumedPushFirestore implements ShouldQueue
{
    public function __construct(
        private readonly FirestoreChatGateway $gateway,
    ) {}

    public function handle(BookingChatResumed $event): void
    {
        $this->gateway->unfreezeThread($event->firestoreThreadId);
    }
}
