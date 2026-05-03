<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Catalog\Application\Listeners\ArchiveServicesOnTypeRevokedListener;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentCatalogServiceReader;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentPaymentsCatalogReader;
use App\Modules\Catalog\Infrastructure\Repositories\EloquentServiceRatingWriter;
use App\Modules\Identity\Domain\Events\VendorTypeRevoked;
use App\Modules\Payments\Domain\Contracts\PaymentsCatalogReader;
use App\Modules\Reviews\Domain\Contracts\ServiceRatingWriter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogServiceReader::class, EloquentCatalogServiceReader::class);
        $this->app->bind(PaymentsCatalogReader::class, EloquentPaymentsCatalogReader::class);
        $this->app->singleton(ServiceRatingWriter::class, EloquentServiceRatingWriter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'catalog');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'catalog');
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        Event::listen(VendorTypeRevoked::class, ArchiveServicesOnTypeRevokedListener::class);
    }
}
