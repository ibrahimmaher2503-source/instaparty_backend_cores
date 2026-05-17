<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions;

use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

final class CancelActiveBookingTransition extends Transition
{
    public function __construct(
        private readonly Booking $model,
        private readonly int $actorId,
        private readonly string $reason,
    ) {}

    public function handle(): Booking
    {
        abort_unless(
            auth()->user()?->hasRole('admin'),
            403,
            'Only admins can cancel an active booking'
        );

        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Admin->value);
        Context::add('transition_reason', $this->reason);

        $this->model->cancelled_at = now();
        $this->model->cancelled_by = 'admin';
        $this->model->save();

        $this->model->lifecycle_status->transitionTo(CancelledState::class);

        DB::afterCommit(fn () => event(new BookingCancelled($this->model)));

        return $this->model;
    }
}
