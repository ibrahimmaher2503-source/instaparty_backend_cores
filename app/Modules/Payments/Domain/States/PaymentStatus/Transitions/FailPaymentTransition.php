<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\States\PaymentStatus\Transitions;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\FailedState;
use App\Modules\Shared\Domain\Enums\TriggerKind;
use Illuminate\Support\Facades\Context;
use Spatie\ModelStates\Transition;

final class FailPaymentTransition extends Transition
{
    public function __construct(
        private readonly Payment $model,
        private readonly ?string $failureCode = null,
        private readonly ?array $failureMessage = null,
    ) {}

    public function handle(): Payment
    {
        Context::add('trigger_kind', TriggerKind::System->value);

        if ($this->failureCode !== null) {
            $this->model->failure_code = $this->failureCode;
        }
        if ($this->failureMessage !== null) {
            $this->model->failure_message = $this->failureMessage;
        }
        $this->model->save();

        $this->model->status->transitionTo(FailedState::class);

        return $this->model;
    }
}
