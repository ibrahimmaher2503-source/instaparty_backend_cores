<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('append-only payment tables have no deleted_at', function (): void {
    expect(Schema::hasColumn('payments', 'deleted_at'))->toBeFalse();
    expect(Schema::hasColumn('payment_attempts', 'deleted_at'))->toBeFalse();
    expect(Schema::hasColumn('gateway_webhook_logs', 'deleted_at'))->toBeFalse();
});
