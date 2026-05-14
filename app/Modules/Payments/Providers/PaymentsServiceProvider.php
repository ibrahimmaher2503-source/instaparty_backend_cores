<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Booking\Application\Listeners\HandlePaymentFailedListener;
use App\Modules\Booking\Application\Listeners\ReleaseInventoryOnPaymentVoidedListener;
use App\Modules\Booking\Application\Listeners\UpdateBookingPaymentStatusListener;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Payments\Application\Listeners\OnBookingForceCancelledInitiateRefundListener;
use App\Modules\Payments\Console\Commands\PingGatewayHealthCommand;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\ChargebackOpened;
use App\Modules\Payments\Domain\Events\ChargebackResolved;
use App\Modules\Payments\Domain\Events\PaymentAbandoned;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Events\PaymentVoided;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Http\Middleware\IdempotencyKeyMiddleware;
use App\Modules\Payments\Infrastructure\Gateways\PaymobGateway;
use App\Modules\Payments\Infrastructure\Repositories\EloquentSettlementPaymentReader;
use App\Modules\Settlement\Application\Listeners\HandleChargebackResolvedListener;
use App\Modules\Settlement\Application\Listeners\ReverseWalletCreditOnChargebackOpenedListener;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PaymobGateway::class);
        $this->app->bind(SettlementPaymentReader::class, EloquentSettlementPaymentReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'payments');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'payments');

        $this->commands([
            PingGatewayHealthCommand::class,
        ]);

        Route::middlewareGroup('idempotency', [IdempotencyKeyMiddleware::class]);
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/webhook.php');

        Event::listen(PaymentCaptured::class, [UpdateBookingPaymentStatusListener::class, 'handle']);
        Event::listen(RefundCompleted::class, [UpdateBookingPaymentStatusListener::class, 'handleRefund']);
        Event::listen(PaymentFailed::class, [HandlePaymentFailedListener::class, 'handle']);
        Event::listen(BookingForceCancelled::class, OnBookingForceCancelledInitiateRefundListener::class);

        // Phase 4.3 — Payments Ops Console
        Event::listen(PaymentVoided::class, [ReleaseInventoryOnPaymentVoidedListener::class, 'handle']);
        Event::listen(PaymentAbandoned::class, [ReleaseInventoryOnPaymentVoidedListener::class, 'handle']);
        Event::listen(ChargebackOpened::class, [ReverseWalletCreditOnChargebackOpenedListener::class, 'handle']);
        Event::listen(ChargebackResolved::class, [HandleChargebackResolvedListener::class, 'handle']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PingGatewayHealthCommand::class)
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }
}
