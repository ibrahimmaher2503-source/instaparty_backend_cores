<?php

declare(strict_types=1);

namespace App\Modules\Geography\Providers;

use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use App\Modules\Geography\Infrastructure\Repositories\EloquentGeographyRepository;
use Illuminate\Support\ServiceProvider;

class GeographyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GeographyRepository::class, EloquentGeographyRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'geography');
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
    }
}
