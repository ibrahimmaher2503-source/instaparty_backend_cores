<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Providers;

use App\Modules\Loyalty\Application\Listeners\ApplyRedemptionOnPaymentCaptured;
use App\Modules\Loyalty\Application\Listeners\CreditPointsOnBookingCompleted;
use App\Modules\Loyalty\Application\Listeners\VoidRedemptionOnBookingCancelled;
use App\Modules\Loyalty\Application\Listeners\ReverseRedemptionOnBookingRefunded;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Infrastructure\Repositories\EloquentLoyaltyProgramRepository;
use App\Modules\Loyalty\Infrastructure\Repositories\EloquentLoyaltyRuleRepository;
use App\Modules\Loyalty\Infrastructure\Repositories\EloquentLoyaltyLedgerRepository;
use App\Modules\Loyalty\Infrastructure\Repositories\EloquentLoyaltyRedemptionRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoyaltyProgramRepository::class, EloquentLoyaltyProgramRepository::class);
        $this->app->singleton(LoyaltyRuleRepository::class, EloquentLoyaltyRuleRepository::class);
        $this->app->singleton(LoyaltyLedgerRepository::class, EloquentLoyaltyLedgerRepository::class);
        $this->app->singleton(LoyaltyRedemptionRepository::class, EloquentLoyaltyRedemptionRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'loyalty');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../Routes/vendor.php');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../Routes/customer.php');

        Route::middleware(['api'])
            ->prefix('api/v1')
            ->group(__DIR__.'/../Routes/admin.php');

        // Consume events from other modules
        Event::listen(
            \App\Modules\Booking\Domain\Events\BookingCompleted::class,
            CreditPointsOnBookingCompleted::class,
        );

        Event::listen(
            \App\Modules\Booking\Domain\Events\BookingCancelled::class,
            VoidRedemptionOnBookingCancelled::class,
        );

        Event::listen(
            \App\Modules\Payments\Domain\Events\RefundFinalized::class,
            ReverseRedemptionOnBookingRefunded::class,
        );

        Event::listen(
            \App\Modules\Communication\Domain\Events\PaymentCaptured::class,
            ApplyRedemptionOnPaymentCaptured::class,
        );
    }
}
