<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus;

final class CompletedState extends BookingLifecycleState
{
    public static string $name = 'completed';
}
