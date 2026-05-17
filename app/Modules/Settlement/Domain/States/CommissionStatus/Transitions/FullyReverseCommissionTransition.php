<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\CommissionStatus\Transitions;

use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\States\CommissionStatus\ReversedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class FullyReverseCommissionTransition extends Transition
{
    public function __construct(
        private readonly Commission $model,
    ) {}

    public function handle(): Commission
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->reversed_amount_minor = $this->model->commission_minor;
        $this->model->save();

        $this->model->status->transitionTo(ReversedState::class);

        return $this->model;
    }
}
