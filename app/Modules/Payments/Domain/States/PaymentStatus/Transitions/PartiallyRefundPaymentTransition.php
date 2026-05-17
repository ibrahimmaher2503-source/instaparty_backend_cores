<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\States\PaymentStatus\Transitions;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PartiallyRefundedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class PartiallyRefundPaymentTransition extends Transition
{
    public function __construct(
        private readonly Payment $model,
    ) {}

    public function handle(): Payment
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->status->transitionTo(PartiallyRefundedState::class);

        return $this->model;
    }
}
