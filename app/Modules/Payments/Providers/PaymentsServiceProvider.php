<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Booking\Application\Listeners\HandlePaymentFailedListener;
use App\Modules\Booking\Application\Listeners\UpdateBookingPaymentStatusListener;
use App\Modules\Payments\Application\Actions\ExpirePendingPaymentsAction;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Http\Middleware\IdempotencyKeyMiddleware;
use App\Modules\Payments\Infrastructure\Gateways\PaymobGateway;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PaymobGateway::class);
        $this->app->singleton('router', fn ($app) => $app['router']);
    }

    public function boot(): void
    {
        Route::middlewareGroup('idempotency', [IdempotencyKeyMiddleware::class]);
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/webhook.php');

        Event::listen(PaymentCaptured::class, [UpdateBookingPaymentStatusListener::class, 'handle']);
        Event::listen(RefundCompleted::class, [UpdateBookingPaymentStatusListener::class, 'handleRefund']);
        Event::listen(PaymentFailed::class, [HandlePaymentFailedListener::class, 'handle']);

        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->call(fn () => app(ExpirePendingPaymentsAction::class)->execute())->everyFiveMinutes();
        });
    }
}
