<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

enum InvoiceStatus: string
{
    case Pending  = 'pending';
    case Paid     = 'paid';
    case Failed   = 'failed';
    case Refunded = 'refunded';
}
