<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Policies\BookingPolicy;

it('BookingPolicy has a public method assignReplacementVendor', function (): void {
    $reflection = new ReflectionClass(BookingPolicy::class);

    expect($reflection->hasMethod('assignReplacementVendor'))->toBeTrue(
        'BookingPolicy must declare assignReplacementVendor (FR-EXT-021)'
    );

    $method = $reflection->getMethod('assignReplacementVendor');
    expect($method->isPublic())->toBeTrue('assignReplacementVendor must be public');
})->group('architecture');

it('assignReplacementVendor has the literal false return type', function (): void {
    $method = (new ReflectionClass(BookingPolicy::class))->getMethod('assignReplacementVendor');
    $returnType = $method->getReturnType();

    expect($returnType)->not->toBeNull('return type must be declared');
    expect((string) $returnType)->toBe('false',
        'Return type must be the literal false pseudo-type, not bool or mixed'
    );
})->group('architecture');

it('invoking assignReplacementVendor always returns false', function (): void {
    $policy = new BookingPolicy();
    $booking = new Booking();
    $booking->id = 0;

    // null user (guest path) — DB insert will fail silently via try/catch in helper
    $result = $policy->assignReplacementVendor(null, $booking);

    expect($result)->toBeFalse();
})->group('architecture');
