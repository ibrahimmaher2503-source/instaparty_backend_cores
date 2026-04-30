<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\RentalItemStatus;

class OutForDeliveryState extends RentalItemStatus
{
    public static string $name = 'out_for_delivery';
}
