<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingDraftCreated;
use App\Modules\Booking\Domain\Models\BookingStateTransition;

class WriteBookingStateTransitionListener
{
    public function handle(BookingDraftCreated $event): void
    {
        BookingStateTransition::create([
            'transitionable_type' => get_class($event->booking),
            'transitionable_id' => $event->booking->id,
            'from_state' => null,
            'to_state' => 'draft',
            'trigger_kind' => 'system',
        ]);
    }
}
