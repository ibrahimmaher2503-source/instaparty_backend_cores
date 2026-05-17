<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Application\Listeners\StateTransitionObserver;
use App\Modules\Shared\Domain\Contracts\StateTransitionLogger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\ModelStates\Events\StateChanged;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StateTransitionLogger::class, StateTransitionObserver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'shared');
        $this->registerRoutes();

        Event::listen(StateChanged::class, StateTransitionObserver::class);
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
    }
}
