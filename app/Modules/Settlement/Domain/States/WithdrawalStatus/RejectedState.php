<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\WithdrawalStatus;

final class RejectedState extends WithdrawalState
{
    public static string $name = 'rejected';
}
