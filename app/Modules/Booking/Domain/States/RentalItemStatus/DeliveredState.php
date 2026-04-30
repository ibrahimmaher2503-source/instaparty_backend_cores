<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\RentalItemStatus;

class DeliveredState extends RentalItemStatus
{
    public static string $name = 'delivered';
}
