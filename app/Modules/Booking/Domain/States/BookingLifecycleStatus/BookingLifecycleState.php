<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingLifecycleStatus;

use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\ActivateBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CancelActiveBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CancelFromCustomerReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CancelFromVendorReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\CompleteBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\ConfirmBookingTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\MoveToCustomerReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\SendToVendorReviewTransition;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\Transitions\SubmitBookingTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class BookingLifecycleState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, SubmittedState::class, SubmitBookingTransition::class)
            ->allowTransition(SubmittedState::class, VendorReviewState::class, SendToVendorReviewTransition::class)
            ->allowTransition(VendorReviewState::class, CustomerReviewState::class, MoveToCustomerReviewTransition::class)
            ->allowTransition(VendorReviewState::class, ConfirmedState::class, ConfirmBookingTransition::class)
            ->allowTransition(VendorReviewState::class, CancelledState::class, CancelFromVendorReviewTransition::class)
            ->allowTransition(CustomerReviewState::class, ConfirmedState::class, ConfirmBookingTransition::class)
            ->allowTransition(CustomerReviewState::class, CancelledState::class, CancelFromCustomerReviewTransition::class)
            ->allowTransition(ConfirmedState::class, ActiveState::class, ActivateBookingTransition::class)
            ->allowTransition(ActiveState::class, CompletedState::class, CompleteBookingTransition::class)
            ->allowTransition(ActiveState::class, CancelledState::class, CancelActiveBookingTransition::class);
    }
}
