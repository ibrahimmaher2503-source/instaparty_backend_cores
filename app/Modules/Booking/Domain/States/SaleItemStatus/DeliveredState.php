<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\SaleItemStatus;

class DeliveredState extends SaleItemStatus
{
    public static string $name = 'delivered';
}
