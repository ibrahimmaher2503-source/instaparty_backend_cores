<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\WithdrawalStatus;

final class ApprovedState extends WithdrawalState
{
    public static string $name = 'approved';
}
