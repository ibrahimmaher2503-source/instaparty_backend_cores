<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Providers;

use App\Modules\Identity\Domain\Events\VendorApproved;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Subscriptions\Application\Listeners\OnPaymentCaptured;
use App\Modules\Subscriptions\Application\Listeners\OnSubscriptionExpired;
use App\Modules\Subscriptions\Application\Listeners\OnVendorRegistered;
use App\Modules\Subscriptions\Application\Services\FeatureResolver;
use App\Modules\Subscriptions\Application\Services\SubscriptionPolicy;
use App\Modules\Subscriptions\Domain\Contracts\CommissionTierLookup;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionRepository;
use App\Modules\Subscriptions\Domain\Events\SubscriptionExpired;
use App\Modules\Subscriptions\Infrastructure\Repositories\EloquentSubscriptionRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SubscriptionRepository::class, EloquentSubscriptionRepository::class);
        $this->app->singleton(FeatureResolver::class);
        $this->app->singleton(SubscriptionPolicy::class);
        $this->app->singleton(SubscriptionPolicyContract::class, SubscriptionPolicy::class);
        $this->app->singleton(CommissionTierLookup::class, SubscriptionPolicy::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'subscriptions');

        $this->registerEventListeners();
        $this->registerRoutes();
    }

    private function registerEventListeners(): void
    {
        Event::listen(
            VendorRegistered::class,
            OnVendorRegistered::class,
        );

        Event::listen(
            VendorApproved::class,
            OnVendorRegistered::class,
        );

        Event::listen(
            PaymentCaptured::class,
            OnPaymentCaptured::class,
        );

        Event::listen(
            SubscriptionExpired::class,
            OnSubscriptionExpired::class,
        );
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
    }
}
