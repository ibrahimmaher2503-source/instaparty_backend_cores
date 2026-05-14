<?php

declare(strict_types=1);

// Each assertion is its own statement because `->ignoring(...)` must be the
// terminal call on the chain — chaining a second `->not->toUse(...)` after it
// would trigger the first expectation's verification before `ignoring` propagates.
//
// Factories, seeders, and policies are exempted from the import ban:
// - Factories MUST compose with other modules' factories (User, VendorProfile,
//   Booking) to seed realistic aggregates. The arch ban targets runtime
//   business code, not test scaffolding.
// - Seeders read vendor/customer rows directly to attach sample programs and
//   ledger entries for local development; they are not part of runtime flow.
// - Policies are Filament/Shield-generated and type-hint Identity\User for
//   Laravel's authorization signature. They never touch User business state.

$ignored = [
    'App\\Modules\\Loyalty\\Database\\Factories',
    'App\\Modules\\Loyalty\\Database\\Seeders',
    'App\\Modules\\Loyalty\\Domain\\Policies',
];

it('loyalty module does not import Booking models', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');

it('loyalty module does not import Catalog models', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Catalog\\Domain\\Models')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');

it('loyalty module does not import Settlement models', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Settlement\\Domain\\Models')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');

it('loyalty module does not import Identity models', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Identity\\Domain\\Models')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');

it('loyalty module does not import Payments models', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Payments\\Domain\\Models')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');

it('loyalty module does not import Reviews models', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Reviews\\Domain\\Models')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');

it('loyalty module does not import Communication models', function () use ($ignored): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Communication\\Domain\\Models')
        ->ignoring($ignored);
})->group('architecture', 'loyalty');
