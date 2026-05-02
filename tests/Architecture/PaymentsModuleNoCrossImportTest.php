<?php

declare(strict_types=1);

it('payments module does not import booking domain models', function (): void {
    expect('App\\Modules\\Payments')->not->toUse('App\\Modules\\Booking\\Domain\\Models');
});
