<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\States\PaymentStatus\Transitions;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class RetryPaymentTransition extends Transition
{
    public function __construct(
        private readonly Payment $model,
    ) {}

    public function handle(): Payment
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        $this->model->failure_code = null;
        $this->model->failure_message = null;
        $this->model->save();

        $this->model->status->transitionTo(PendingState::class);

        return $this->model;
    }
}
