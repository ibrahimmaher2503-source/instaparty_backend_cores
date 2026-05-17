<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\States\PaymentStatus;

use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\AbandonPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\AuthorizePaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\CapturePaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\FailPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\PartiallyRefundPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\RefundPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\RetryPaymentTransition;
use App\Modules\Payments\Domain\States\PaymentStatus\Transitions\VoidPaymentTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PaymentState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, AuthorizedState::class, AuthorizePaymentTransition::class)
            ->allowTransition(PendingState::class, FailedState::class, FailPaymentTransition::class)
            ->allowTransition(PendingState::class, AbandonedState::class, AbandonPaymentTransition::class)
            ->allowTransition(AuthorizedState::class, CapturedState::class, CapturePaymentTransition::class)
            ->allowTransition(AuthorizedState::class, VoidedState::class, VoidPaymentTransition::class)
            ->allowTransition(CapturedState::class, PartiallyRefundedState::class, PartiallyRefundPaymentTransition::class)
            ->allowTransition(CapturedState::class, RefundedState::class, RefundPaymentTransition::class)
            ->allowTransition(PartiallyRefundedState::class, RefundedState::class, RefundPaymentTransition::class)
            ->allowTransition(FailedState::class, PendingState::class, RetryPaymentTransition::class);
    }
}
