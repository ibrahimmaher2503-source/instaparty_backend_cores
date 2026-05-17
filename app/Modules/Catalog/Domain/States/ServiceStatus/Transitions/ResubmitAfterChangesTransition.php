<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus\Transitions;

use App\Modules\Catalog\Domain\Events\ServiceReturnedToReview;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class ResubmitAfterChangesTransition extends Transition
{
    public function __construct(
        private readonly Service $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Service
    {
        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Vendor->value);

        $this->model->status = PendingReviewState::class;
        $this->model->save();

        DB::afterCommit(fn () => event(new ServiceReturnedToReview($this->model)));

        return $this->model;
    }
}
