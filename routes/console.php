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
