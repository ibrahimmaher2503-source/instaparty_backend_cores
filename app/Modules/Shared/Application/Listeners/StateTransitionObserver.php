<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Listeners;

use App\Modules\Shared\Domain\Enums\TriggerKind;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Events\StateChanged;

class StateTransitionObserver
{
    public function handle(StateChanged $event): void
    {
        $triggerKindValue = Context::get('trigger_kind', TriggerKind::System->value);
        $triggerKind = TriggerKind::from($triggerKindValue);

        StateTransition::create([
            'transitionable_type' => get_class($event->model),
            'transitionable_id'   => $event->model->getKey(),
            'from_state'          => $event->initialState?->getValue(),
            'to_state'            => $event->finalState->getValue(),
            'triggered_by'        => Context::get('actor_id'),
            'trigger_kind'        => $triggerKind->value,
            'reason'              => Context::get('transition_reason'),
            'trace_id'            => Context::get('trace_id'),
            'context'             => Context::get('transition_metadata'),
        ]);

        Context::forget('transition_reason');
        Context::forget('transition_metadata');
    }
}
