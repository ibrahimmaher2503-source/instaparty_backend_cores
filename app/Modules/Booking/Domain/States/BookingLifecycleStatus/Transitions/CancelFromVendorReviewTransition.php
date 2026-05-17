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

final class CancelFromVendorReviewTransition extends Transition
{
    public function __construct(
        private readonly Booking $model,
        private readonly ?string $reason = null,
    ) {}

    public function handle(): Booking
    {
        Context::add('trigger_kind', TriggerKind::System->value);
        if ($this->reason !== null) {
            Context::add('transition_reason', $this->reason);
        }

        $this->model->cancelled_at = now();
        $this->model->cancelled_by = 'system';
        $this->model->save();

        $this->model->lifecycle_status->transitionTo(CancelledState::class);

        DB::afterCommit(fn () => event(new BookingCancelled($this->model)));

        return $this->model;
    }
}
