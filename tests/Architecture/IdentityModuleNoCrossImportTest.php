<?php

declare(strict_types=1);

// VendorProfile.php pre-dates this check and has a legacy Service import for the HasMany relationship.
// This test guards that no NEW Application/Infrastructure code in Identity imports Catalog models directly.
it('identity application and infrastructure layers do not import catalog domain models', function (): void {
    expect('App\\Modules\\Identity\\Application')
        ->not->toUse('App\\Modules\\Catalog\\Domain\\Models');

    expect('App\\Modules\\Identity\\Infrastructure')
        ->not->toUse('App\\Modules\\Catalog\\Domain\\Models');
})->group('architecture', 'identity');
