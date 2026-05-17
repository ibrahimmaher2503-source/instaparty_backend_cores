<?php

use App\Modules\Payments\Application\Actions\ExpirePendingPaymentsAction;
use App\Modules\Payments\Infrastructure\Repositories\EloquentIdempotencyKeyRepository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('booking:release-expired-reservations')->everyMinute()->withoutOverlapping();

Schedule::call(fn () => app(ExpirePendingPaymentsAction::class)->execute())
    ->name('payments:expire-pending-holds')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(EloquentIdempotencyKeyRepository::class)->purgeExpired())
    ->name('payments:purge-expired-idempotency-keys')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

// Settlement reconciliation — hourly recent-touch and daily full scan
Schedule::command('reconcile:run --scope=recent_touch --window=60min')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reconcile:run --scope=all')
    ->dailyAt('04:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ledger:snapshot --all')
    ->dailyAt('04:30')
    ->timezone('Africa/Cairo')
    ->onOneServer();

// Settlement batch payout run — weekly on Sunday 03:00 Cairo time
Schedule::command('settle:run')
    ->weeklyOn(0, '03:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();
