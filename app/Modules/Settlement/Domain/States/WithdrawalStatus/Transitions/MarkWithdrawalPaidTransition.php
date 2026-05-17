<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\WithdrawalStatus\Transitions;

use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PaidState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class MarkWithdrawalPaidTransition extends Transition
{
    public function __construct(
        private readonly Withdrawal $model,
        private readonly int $actorId,
        private readonly ?int $paidAmountMinor = null,
        private readonly ?string $paidAmountCurrency = null,
    ) {}

    public function handle(): Withdrawal
    {
        abort_unless(
            auth()->user()?->can('approve_withdrawal'),
            403,
            'Insufficient permissions to mark withdrawal as paid'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);

        $this->model->paid_at = now();
        if ($this->paidAmountMinor !== null) {
            $this->model->paid_amount_minor = $this->paidAmountMinor;
        }
        if ($this->paidAmountCurrency !== null) {
            $this->model->paid_amount_currency = $this->paidAmountCurrency;
        }
        $this->model->save();

        $this->model->status->transitionTo(PaidState::class);

        return $this->model;
    }
}
