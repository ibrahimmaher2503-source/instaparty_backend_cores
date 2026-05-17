<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\CustomerReviewReminderSent;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnCustomerReviewReminderSentNotifyCustomer implements ShouldQueue
{
    public function handle(CustomerReviewReminderSent $event): void
    {
        // Notification dispatched inline by ResumeBookingReviewAction for synchronous reliability.
        // This listener exists for future extension (analytics, additional channels).
    }
}
