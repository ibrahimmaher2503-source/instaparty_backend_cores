<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\CommissionStatus\Transitions;

use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\States\CommissionStatus\PartiallyReversedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class PartiallyReverseCommissionTransition extends Transition
{
    public function __construct(
        private readonly Commission $model,
        private readonly int $reversedAmountMinor,
    ) {}

    public function handle(): Commission
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->reversed_amount_minor = $this->reversedAmountMinor;
        $this->model->save();

        $this->model->status->transitionTo(PartiallyReversedState::class);

        return $this->model;
    }
}
