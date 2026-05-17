<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States\Transitions;

use App\Modules\Shared\Domain\Enums\TriggerKind;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Domain\States\PastDueState;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class MarkPastDueTransition extends Transition
{
    public function __construct(
        private readonly VendorSubscription $model,
    ) {}

    public function handle(): VendorSubscription
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->status->transitionTo(PastDueState::class);

        return $this->model;
    }
}
