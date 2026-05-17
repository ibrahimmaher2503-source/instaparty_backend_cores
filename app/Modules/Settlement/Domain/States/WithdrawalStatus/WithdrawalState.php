<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\WithdrawalStatus;

use App\Modules\Settlement\Domain\States\WithdrawalStatus\Transitions\ApproveWithdrawalTransition;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\Transitions\MarkWithdrawalPaidTransition;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\Transitions\RejectWithdrawalTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class WithdrawalState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ApprovedState::class, ApproveWithdrawalTransition::class)
            ->allowTransition(PendingState::class, RejectedState::class, RejectWithdrawalTransition::class)
            ->allowTransition(ApprovedState::class, PaidState::class, MarkWithdrawalPaidTransition::class);
    }
}
