<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\RentalItemStatus;

class PendingDeliveryState extends RentalItemStatus
{
    public static string $name = 'pending_delivery';
}
