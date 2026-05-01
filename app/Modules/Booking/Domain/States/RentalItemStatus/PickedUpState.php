<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\RentalItemStatus;

class PickedUpState extends RentalItemStatus
{
    public static string $name = 'picked_up';
}
