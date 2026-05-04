<?php

declare(strict_types=1);
use App\Modules\Discovery\Domain\Models\SearchLog;

test('Discovery module does not import Booking or Payments models')
    ->expect('App\Modules\Discovery')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Payments\Domain\Models',
        'App\Modules\Settlement\Domain\Models',
    ]);

test('SearchLog has no updated_at', function () {
    expect(SearchLog::UPDATED_AT)->toBeNull();
});
