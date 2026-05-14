<?php

declare(strict_types=1);

// Factories are exempt — they reference Booking::factory() purely to compose
// test fixtures, never to mutate real booking state. The runtime ban (Actions,
// Listeners, Services, Repositories) is preserved.
$ignored = ['App\\Modules\\Loyalty\\Database\\Factories'];

it('loyalty module does not mutate Booking model directly', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models\\Booking')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');

it('loyalty module does not mutate BookingItem model directly', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models\\BookingItem')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');
