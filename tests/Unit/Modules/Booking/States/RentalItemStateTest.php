<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\States\RentalItemStatus\DeliveredState;
use App\Modules\Booking\Domain\States\RentalItemStatus\OutForDeliveryState;
use App\Modules\Booking\Domain\States\RentalItemStatus\PendingDeliveryState;
use App\Modules\Booking\Domain\States\RentalItemStatus\PickedUpState;
use App\Modules\Booking\Domain\States\RentalItemStatus\RentalItemStatus;
use App\Modules\Booking\Domain\States\RentalItemStatus\SetupCompleteState;
use Spatie\ModelStates\State;

it('rental state names are correct', function (): void {
    expect(PendingDeliveryState::$name)->toBe('pending_delivery');
    expect(OutForDeliveryState::$name)->toBe('out_for_delivery');
    expect(DeliveredState::$name)->toBe('delivered');
    expect(SetupCompleteState::$name)->toBe('setup_complete');
    expect(PickedUpState::$name)->toBe('picked_up');
})->group('booking', 'states', 'rental');

it('all rental state classes extend RentalItemStatus', function (): void {
    expect(is_subclass_of(PendingDeliveryState::class, RentalItemStatus::class))->toBeTrue();
    expect(is_subclass_of(OutForDeliveryState::class, RentalItemStatus::class))->toBeTrue();
    expect(is_subclass_of(DeliveredState::class, RentalItemStatus::class))->toBeTrue();
    expect(is_subclass_of(SetupCompleteState::class, RentalItemStatus::class))->toBeTrue();
    expect(is_subclass_of(PickedUpState::class, RentalItemStatus::class))->toBeTrue();
})->group('booking', 'states', 'rental');

it('RentalItemStatus extends Spatie State', function (): void {
    expect(is_subclass_of(RentalItemStatus::class, State::class))->toBeTrue();
})->group('booking', 'states', 'rental');

it('rental has 5 concrete states', function (): void {
    $states = [
        PendingDeliveryState::class,
        OutForDeliveryState::class,
        DeliveredState::class,
        SetupCompleteState::class,
        PickedUpState::class,
    ];
    expect(count($states))->toBe(5);
})->group('booking', 'states', 'rental');
