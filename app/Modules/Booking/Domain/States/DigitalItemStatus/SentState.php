<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\DigitalItemStatus;

class SentState extends DigitalItemStatus
{
    public static string $name = 'sent';
}
