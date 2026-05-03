<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Booking\Application\Listeners\HandlePaymentFailedListener;
use App\Modules\Booking\Application\Listeners\UpdateBookingPaymentStatusListener;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Infrastructure\Repositories\EloquentSettlementPaymentReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Http\Middleware\IdempotencyKeyMiddleware;
use App\Modules\Payments\Infrastructure\Gateways\PaymobGateway;
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

        Route::middlewareGroup('idempotency', [IdempotencyKeyMiddleware::class]);
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/webhook.php');

        Event::listen(PaymentCaptured::class, [UpdateBookingPaymentStatusListener::class, 'handle']);
        Event::listen(RefundCompleted::class, [UpdateBookingPaymentStatusListener::class, 'handleRefund']);
        Event::listen(PaymentFailed::class, [HandlePaymentFailedListener::class, 'handle']);
    }
}
