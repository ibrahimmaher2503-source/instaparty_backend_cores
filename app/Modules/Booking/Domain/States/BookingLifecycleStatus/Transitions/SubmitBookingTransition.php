<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\SubmittedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class SubmitBookingTransition extends Transition
{
    public function __construct(
        private readonly Booking $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Booking
    {
        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Customer->value);

        $this->model->submitted_at = now();
        $this->model->save();

        $this->model->lifecycle_status->transitionTo(SubmittedState::class);

        return $this->model;
    }
}
