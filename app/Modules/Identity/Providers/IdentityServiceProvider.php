<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Application\Listeners\RevokeAllVendorTypesOnStatusChange;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Events\VendorRejected;
use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Http\Middleware\EnsureAccountIsActive;
use App\Modules\Identity\Infrastructure\Gateways\StubOtpGateway;
use App\Modules\Identity\Infrastructure\Repositories\EloquentVendorRatingWriter;
use App\Modules\Reviews\Domain\Contracts\VendorRatingWriter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpGatewayInterface::class, StubOtpGateway::class);
        $this->app->singleton(VendorRatingWriter::class, EloquentVendorRatingWriter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'identity');

        $this->registerEventListeners();
        $this->registerRoutes();

        $this->app['router']->aliasMiddleware('ensure.account.active', EnsureAccountIsActive::class);
    }

    private function registerEventListeners(): void
    {
        Event::listen(VendorSuspended::class, RevokeAllVendorTypesOnStatusChange::class);
        Event::listen(VendorRejected::class, RevokeAllVendorTypesOnStatusChange::class);
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
    }
}
