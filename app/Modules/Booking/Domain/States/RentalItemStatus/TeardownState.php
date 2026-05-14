<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\RentalItemStatus;

class TeardownState extends RentalItemStatus
{
    public static string $name = 'teardown';
}
