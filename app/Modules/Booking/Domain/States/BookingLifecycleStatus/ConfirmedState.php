<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus;

final class ConfirmedState extends BookingLifecycleState
{
    public static string $name = 'confirmed';
}
