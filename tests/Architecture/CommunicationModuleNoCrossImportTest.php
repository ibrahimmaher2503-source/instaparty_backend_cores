<?php

declare(strict_types=1);

// T077 — Communication module must not import Eloquent models from other modules.
// Cross-module data access must go through contracts or DTOs per modules.md rules.

it('communication module does not import other modules domain models', function (): void {
    expect('App\\Modules\\Communication')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models')
        ->not->toUse('App\\Modules\\Catalog\\Domain\\Models')
        ->not->toUse('App\\Modules\\Payments\\Domain\\Models')
        ->not->toUse('App\\Modules\\Settlement\\Domain\\Models')
        ->not->toUse('App\\Modules\\Reviews\\Domain\\Models')
        ->not->toUse('App\\Modules\\Geography\\Domain\\Models');
});
