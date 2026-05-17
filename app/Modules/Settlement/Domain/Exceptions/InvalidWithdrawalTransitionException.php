<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use App\Modules\Settlement\Domain\States\WithdrawalStatus\WithdrawalState;

final class InvalidWithdrawalTransitionException extends \DomainException
{
    public function __construct(string $current, string $attempted)
    {
        parent::__construct(
            __('settlement.errors.withdrawal_state', [
                'current'   => $current,
                'attempted' => $attempted,
            ])
        );
    }
}
