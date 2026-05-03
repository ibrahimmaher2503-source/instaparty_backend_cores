<?php

declare(strict_types=1);

it('loyalty module does not mutate booking or booking_items directly', function (): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models\\Booking')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models\\BookingItem');
})->group('architecture', 'loyalty');
