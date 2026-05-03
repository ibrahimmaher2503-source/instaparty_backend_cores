<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentAttempt;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
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

// ─────────────────────────────────────────────────────
// T701 — Settlement append-only models
// ─────────────────────────────────────────────────────

it('wallet_ledger table has no deleted_at column', function (): void {
    expect(Schema::hasColumn('wallet_ledger', 'deleted_at'))->toBeFalse();
});

it('commissions table has no deleted_at column', function (): void {
    expect(Schema::hasColumn('commissions', 'deleted_at'))->toBeFalse();
});

it('wallet_ledger table has no updated_at column (true append-only)', function (): void {
    expect(Schema::hasColumn('wallet_ledger', 'updated_at'))->toBeFalse();
});

it('commissions table has no updated_at column (true append-only)', function (): void {
    expect(Schema::hasColumn('commissions', 'updated_at'))->toBeFalse();
});

it('settlement append-only models do not use the SoftDeletes trait', function (): void {
    foreach ([WalletLedgerEntry::class, Commission::class] as $model) {
        expect(in_array(SoftDeletes::class, class_uses_recursive($model), true))
            ->toBeFalse("$model must not use SoftDeletes trait");
    }
});

// ─────────────────────────────────────────────────────
// T078 — Communication append-only models
// ─────────────────────────────────────────────────────

it('notification_dispatches table has no deleted_at column', function (): void {
    expect(Schema::hasColumn('notification_dispatches', 'deleted_at'))->toBeFalse();
});

it('notification_dispatches table has no updated_at column (true append-only)', function (): void {
    expect(Schema::hasColumn('notification_dispatches', 'updated_at'))->toBeFalse();
});

it('NotificationDispatch model does not use the SoftDeletes trait', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(NotificationDispatch::class), true))
        ->toBeFalse('NotificationDispatch must not use SoftDeletes trait');
});
