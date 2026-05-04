<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingStalled;
use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;

class OnBookingStalledInbox
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(BookingStalled $event): void
    {
        $this->router->execute(
            eventKey: 'booking.stalled',
            severity: AdminInboxSeverity::Warning,
            sourceType: 'booking',
            sourceId: $event->bookingId,
            title: ['en' => 'Booking stalled', 'ar' => 'حجز متوقف'],
            body: ['en' => "Booking #{$event->bookingId} has exceeded the vendor response SLA.", 'ar' => "الحجز #{$event->bookingId} تجاوز مهلة استجابة المورد."],
        );
    }
}
