<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingPaymentStatus;

use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\CompleteRefundTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\InitiateRefundTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\MarkBookingPaidTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\RecordPartialPaymentTransition;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\Transitions\RecordPartialRefundTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class BookingPaymentState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(UnpaidState::class)
            ->allowTransition(UnpaidState::class, PartialState::class, RecordPartialPaymentTransition::class)
            ->allowTransition(UnpaidState::class, PaidState::class, MarkBookingPaidTransition::class)
            ->allowTransition(PartialState::class, PaidState::class, MarkBookingPaidTransition::class)
            ->allowTransition(PaidState::class, RefundPendingState::class, InitiateRefundTransition::class)
            ->allowTransition(RefundPendingState::class, PartiallyRefundedState::class, RecordPartialRefundTransition::class)
            ->allowTransition(RefundPendingState::class, RefundedState::class, CompleteRefundTransition::class)
            ->allowTransition(PartiallyRefundedState::class, RefundedState::class, CompleteRefundTransition::class);
    }
}
