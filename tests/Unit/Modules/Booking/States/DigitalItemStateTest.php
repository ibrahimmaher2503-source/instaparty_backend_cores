<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\States\DigitalItemStatus\DigitalItemStatus;
use App\Modules\Booking\Domain\States\DigitalItemStatus\PendingState;
use App\Modules\Booking\Domain\States\DigitalItemStatus\RedeemedState;
use App\Modules\Booking\Domain\States\DigitalItemStatus\SentState;
use Spatie\ModelStates\State;

it('digital state names are correct', function (): void {
    expect(PendingState::$name)->toBe('pending');
    expect(SentState::$name)->toBe('sent');
    expect(RedeemedState::$name)->toBe('redeemed');
})->group('booking', 'states', 'digital');

it('all digital state classes extend DigitalItemStatus', function (): void {
    expect(is_subclass_of(PendingState::class, DigitalItemStatus::class))->toBeTrue();
    expect(is_subclass_of(SentState::class, DigitalItemStatus::class))->toBeTrue();
    expect(is_subclass_of(RedeemedState::class, DigitalItemStatus::class))->toBeTrue();
})->group('booking', 'states', 'digital');

it('DigitalItemStatus extends Spatie State', function (): void {
    expect(is_subclass_of(DigitalItemStatus::class, State::class))->toBeTrue();
})->group('booking', 'states', 'digital');

it('digital has 3 concrete states', function (): void {
    $states = [PendingState::class, SentState::class, RedeemedState::class];
    expect(count($states))->toBe(3);
})->group('booking', 'states', 'digital');
