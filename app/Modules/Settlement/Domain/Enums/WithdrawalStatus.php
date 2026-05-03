<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
