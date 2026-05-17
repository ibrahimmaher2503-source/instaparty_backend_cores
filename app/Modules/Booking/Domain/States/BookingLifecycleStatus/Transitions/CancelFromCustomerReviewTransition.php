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

final class CancelFromCustomerReviewTransition extends Transition
{
    public function __construct(
        private readonly Booking $model,
        private readonly int $actorId,
    ) {}

    public function handle(): Booking
    {
        Context::add('actor_id', $this->actorId);
        Context::add('trigger_kind', TriggerKind::Customer->value);

        $this->model->cancelled_at = now();
        $this->model->cancelled_by = 'customer';
        $this->model->save();

        $this->model->lifecycle_status->transitionTo(CancelledState::class);

        DB::afterCommit(fn () => event(new BookingCancelled($this->model)));

        return $this->model;
    }
}
