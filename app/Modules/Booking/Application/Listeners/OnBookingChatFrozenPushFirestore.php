<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingChatFrozen;
use App\Modules\Communication\Domain\Contracts\FirestoreChatGateway;
use Illuminate\Contracts\Queue\ShouldQueue;

final class OnBookingChatFrozenPushFirestore implements ShouldQueue
{
    public function __construct(
        private readonly FirestoreChatGateway $gateway,
    ) {}

    public function handle(BookingChatFrozen $event): void
    {
        $this->gateway->freezeThread($event->firestoreThreadId);
    }
}
