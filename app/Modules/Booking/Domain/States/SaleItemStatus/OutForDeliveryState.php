<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\SaleItemStatus;

class OutForDeliveryState extends SaleItemStatus
{
    public static string $name = 'out_for_delivery';
}
