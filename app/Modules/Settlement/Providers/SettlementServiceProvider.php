<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Providers;

use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Settlement\Application\Listeners\CalculateCommissionOnPaymentCapturedListener;
use App\Modules\Settlement\Application\Listeners\ReverseCommissionOnRefundCompletedListener;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRateResolver;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWithdrawalRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SettlementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CommissionRateResolver::class, EloquentCommissionRateResolver::class);
        $this->app->bind(EloquentWalletRepository::class);
        $this->app->bind(EloquentWithdrawalRepository::class);
        $this->app->bind(EloquentCommissionRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'settlement');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');

        // T104 — Trigger commission calculation when a payment is captured
        Event::listen(
            PaymentCaptured::class,
            [CalculateCommissionOnPaymentCapturedListener::class, 'handle'],
        );

        // T502 — Reverse commissions when a refund completes
        Event::listen(
            RefundCompleted::class,
            [ReverseCommissionOnRefundCompletedListener::class, 'handle'],
        );
    }
}
