<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus;

use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ApproveServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ArchiveServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\RejectServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\RequestServiceChangesTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ResubmitAfterChangesTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\SubmitForReviewTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\UnarchiveServiceTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ServiceState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, PendingReviewState::class, SubmitForReviewTransition::class)
            ->allowTransition(PendingReviewState::class, PublishedState::class, ApproveServiceTransition::class)
            ->allowTransition(PendingReviewState::class, RejectedState::class, RejectServiceTransition::class)
            ->allowTransition(PendingReviewState::class, ChangesRequestedState::class, RequestServiceChangesTransition::class)
            ->allowTransition(PendingReviewState::class, DraftState::class)
            ->allowTransition(ChangesRequestedState::class, PendingReviewState::class, ResubmitAfterChangesTransition::class)
            ->allowTransition(ChangesRequestedState::class, DraftState::class)
            ->allowTransition(PublishedState::class, PendingReviewState::class)
            ->allowTransition(PublishedState::class, ArchivedState::class, ArchiveServiceTransition::class)
            ->allowTransition(PublishedState::class, DraftState::class)
            ->allowTransition(RejectedState::class, ArchivedState::class)
            ->allowTransition(ArchivedState::class, DraftState::class, UnarchiveServiceTransition::class);
    }
}
