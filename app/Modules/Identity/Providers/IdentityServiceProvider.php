<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Booking\Domain\Contracts\CoverageAreaResolver;
use App\Modules\Identity\Application\Listeners\RevokeAllVendorTypesOnStatusChange;
use App\Modules\Identity\Application\Listeners\SendDocExpiredNotificationListener;
use App\Modules\Identity\Application\Timeline\VendorProfileTimelineDescriptors;
use App\Modules\Identity\Console\Commands\CheckDocumentExpiryCommand;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use App\Modules\Identity\Domain\Events\VendorRejected;
use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Middleware\EnsureAccountIsActive;
use App\Modules\Identity\Infrastructure\Gateways\StubOtpGateway;
use App\Modules\Identity\Infrastructure\Loyalty\EloquentVendorLookup;
use App\Modules\Identity\Infrastructure\Repositories\EloquentCoverageAreaResolver;
use App\Modules\Identity\Infrastructure\Repositories\EloquentVendorRatingWriter;
use App\Modules\Loyalty\Domain\Contracts\VendorLookup;
use App\Modules\Reviews\Domain\Contracts\VendorRatingWriter;
use App\Modules\Shared\Application\Timeline\TimelineSourceRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpGatewayInterface::class, StubOtpGateway::class);
        $this->app->singleton(VendorRatingWriter::class, EloquentVendorRatingWriter::class);
        $this->app->singleton(VendorLookup::class, EloquentVendorLookup::class);
        $this->app->bind(CoverageAreaResolver::class, EloquentCoverageAreaResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'identity');

        $this->commands([
            CheckDocumentExpiryCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('identity:check-document-expiry')->dailyAt('02:00');
        });

        $this->registerEventListeners();
        $this->registerRoutes();

        $this->callAfterResolving(TimelineSourceRegistry::class, function (TimelineSourceRegistry $registry): void {
            $registry->register(VendorProfile::class, ...VendorProfileTimelineDescriptors::all());
        });

        $this->app['router']->aliasMiddleware('ensure.account.active', EnsureAccountIsActive::class);
        $this->app['router']->aliasMiddleware('vendor.not_suspended', \App\Modules\Identity\Http\Middleware\EnsureVendorNotSuspended::class);
    }

    private function registerEventListeners(): void
    {
        Event::listen(Login::class, UpdateLastLoginAtListener::class);
        Event::listen(VendorSuspended::class, RevokeAllVendorTypesOnStatusChange::class);
        Event::listen(VendorRejected::class, RevokeAllVendorTypesOnStatusChange::class);
        Event::listen(VendorAutoSuspended::class, RevokeAllVendorTypesOnStatusChange::class);
        Event::listen(VendorAutoSuspended::class, SendDocExpiredNotificationListener::class);
        Event::listen(PasswordResetRequested::class, SendPasswordResetNotification::class);
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
    }
}
