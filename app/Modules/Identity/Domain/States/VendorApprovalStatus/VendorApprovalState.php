<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus;

use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\ApproveVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\RejectVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\RequestVendorChangesTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\ResetVendorToPendingTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\SuspendVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\UnsuspendVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\VendorResubmitTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class VendorApprovalState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ApprovedState::class, ApproveVendorTransition::class)
            ->allowTransition(PendingState::class, RejectedState::class, RejectVendorTransition::class)
            ->allowTransition(PendingState::class, ChangesRequestedState::class, RequestVendorChangesTransition::class)
            ->allowTransition(ChangesRequestedState::class, PendingState::class, VendorResubmitTransition::class)
            ->allowTransition(ApprovedState::class, SuspendedState::class, SuspendVendorTransition::class)
            ->allowTransition(SuspendedState::class, ApprovedState::class, UnsuspendVendorTransition::class)
            ->allowTransition(RejectedState::class, PendingState::class, ResetVendorToPendingTransition::class);
    }
}
