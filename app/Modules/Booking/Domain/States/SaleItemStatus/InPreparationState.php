<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\SaleItemStatus;

class InPreparationState extends SaleItemStatus
{
    public static string $name = 'in_preparation';
}
