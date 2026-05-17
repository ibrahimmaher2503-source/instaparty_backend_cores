<?php

declare(strict_types=1);

namespace App\Modules\Tax\Providers;

use App\Modules\Booking\Domain\Contracts\TaxRateResolver;
use App\Modules\Tax\Domain\Contracts\TaxRateRepository;
use App\Modules\Tax\Infrastructure\Repositories\EloquentTaxRateRepository;
use App\Modules\Tax\Infrastructure\Resolvers\BookingTaxRateResolver;
use Illuminate\Support\ServiceProvider;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaxRateRepository::class, EloquentTaxRateRepository::class);
        $this->app->bind(TaxRateResolver::class, BookingTaxRateResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'tax');
        $this->loadViewsFrom(__DIR__ . '/../../../../resources/views/vendor/tax', 'tax');
    }
}
