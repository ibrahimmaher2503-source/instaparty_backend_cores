<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\WithdrawalStatus\Transitions;

use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\ApprovedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class ApproveWithdrawalTransition extends Transition
{
    public function __construct(
        private readonly Withdrawal $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Withdrawal
    {
        abort_unless(
            auth()->user()?->can('approve_withdrawal'),
            403,
            'Insufficient permissions to approve withdrawal'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->processed_by_user_id = $this->actorId;
        $this->model->processed_at = now();
        $this->model->save();

        $this->model->status->transitionTo(ApprovedState::class);

        return $this->model;
    }
}
