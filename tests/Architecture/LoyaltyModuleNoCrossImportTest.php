<?php

declare(strict_types=1);

it('loyalty module does not import other modules domain models', function (): void {
    expect('App\\Modules\\Loyalty')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models')
        ->not->toUse('App\\Modules\\Catalog\\Domain\\Models')
        ->not->toUse('App\\Modules\\Settlement\\Domain\\Models')
        ->not->toUse('App\\Modules\\Identity\\Domain\\Models')
        ->not->toUse('App\\Modules\\Payments\\Domain\\Models')
        ->not->toUse('App\\Modules\\Reviews\\Domain\\Models')
        ->not->toUse('App\\Modules\\Communication\\Domain\\Models');
})->group('architecture', 'loyalty');
