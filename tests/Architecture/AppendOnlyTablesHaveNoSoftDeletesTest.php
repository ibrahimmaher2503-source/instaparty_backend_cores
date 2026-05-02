<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

it('append-only payment tables have no deleted_at column', function (): void {
    expect(Schema::hasColumn('payments', 'deleted_at'))->toBeFalse();
    expect(Schema::hasColumn('payment_attempts', 'deleted_at'))->toBeFalse();
    expect(Schema::hasColumn('gateway_webhook_logs', 'deleted_at'))->toBeFalse();
});

it('payment_attempts and gateway_webhook_logs have no updated_at column (true append-only)', function (): void {
    expect(Schema::hasColumn('payment_attempts', 'updated_at'))->toBeFalse();
    expect(Schema::hasColumn('gateway_webhook_logs', 'updated_at'))->toBeFalse();
});

it('append-only payment models do not use the SoftDeletes trait', function (): void {
    foreach ([Payment::class, PaymentAttempt::class, GatewayWebhookLog::class] as $model) {
        expect(in_array(SoftDeletes::class, class_uses_recursive($model), true))
            ->toBeFalse("$model must not use SoftDeletes trait");
    }
});
