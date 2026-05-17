<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\RefundedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class CompleteRefundTransition extends Transition
{
    public function __construct(
        private readonly Booking $model,
    ) {}

    public function handle(): Booking
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->payment_status->transitionTo(RefundedState::class);

        return $this->model;
    }
}
