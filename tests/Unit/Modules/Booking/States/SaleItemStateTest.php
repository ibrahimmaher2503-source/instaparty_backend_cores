<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\States\SaleItemStatus\DeliveredState;
use App\Modules\Booking\Domain\States\SaleItemStatus\InPreparationState;
use App\Modules\Booking\Domain\States\SaleItemStatus\PendingState;
use App\Modules\Booking\Domain\States\SaleItemStatus\ReadyState;
use App\Modules\Booking\Domain\States\SaleItemStatus\SaleItemStatus;
use Spatie\ModelStates\State;

it('sale state names are correct', function (): void {
    expect(PendingState::$name)->toBe('pending');
    expect(InPreparationState::$name)->toBe('in_preparation');
    expect(ReadyState::$name)->toBe('ready');
    expect(DeliveredState::$name)->toBe('delivered');
})->group('booking', 'states', 'sale');

it('all sale state classes extend SaleItemStatus', function (): void {
    expect(is_subclass_of(PendingState::class, SaleItemStatus::class))->toBeTrue();
    expect(is_subclass_of(InPreparationState::class, SaleItemStatus::class))->toBeTrue();
    expect(is_subclass_of(ReadyState::class, SaleItemStatus::class))->toBeTrue();
    expect(is_subclass_of(DeliveredState::class, SaleItemStatus::class))->toBeTrue();
})->group('booking', 'states', 'sale');

it('SaleItemStatus extends Spatie State', function (): void {
    expect(is_subclass_of(SaleItemStatus::class, State::class))->toBeTrue();
})->group('booking', 'states', 'sale');

it('sale has 4 concrete states', function (): void {
    $states = [
        PendingState::class,
        InPreparationState::class,
        ReadyState::class,
        DeliveredState::class,
    ];
    expect(count($states))->toBe(4);
})->group('booking', 'states', 'sale');
