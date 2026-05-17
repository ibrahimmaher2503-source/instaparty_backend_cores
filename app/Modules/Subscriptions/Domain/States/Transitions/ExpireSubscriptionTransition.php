<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States\Transitions;

use App\Modules\Shared\Domain\Enums\TriggerKind;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Domain\States\ExpiredState;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class ExpireSubscriptionTransition extends Transition
{
    public function __construct(
        private readonly VendorSubscription $model,
    ) {}

    public function handle(): VendorSubscription
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->ended_at = now();
        $this->model->ended_reason = 'expired';
        $this->model->save();

        $this->model->status->transitionTo(ExpiredState::class);

        return $this->model;
    }
}
