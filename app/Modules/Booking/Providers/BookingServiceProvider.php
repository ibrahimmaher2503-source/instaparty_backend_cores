<?php

declare(strict_types=1);

namespace App\Modules\Booking\Providers;

use App\Modules\Booking\Application\Listeners\RecalculateBookingTotalsListener;
use App\Modules\Booking\Application\Listeners\WriteBookingStateTransitionListener;
use App\Modules\Booking\Application\Listeners\WriteInitialBookingSnapshotListener;
use App\Modules\Booking\Console\Commands\ReleaseExpiredReservationsCommand;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Events\BookingDraftCreated;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Events\BookingItemRemoved;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookingRepository::class, EloquentBookingRepository::class);
        // CatalogServiceReader is bound by CatalogServiceProvider
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'booking');
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');

        $this->commands([ReleaseExpiredReservationsCommand::class]);

        Event::listen(BookingDraftCreated::class, WriteInitialBookingSnapshotListener::class);
        Event::listen(BookingDraftCreated::class, WriteBookingStateTransitionListener::class);
        Event::listen(BookingItemAdded::class, RecalculateBookingTotalsListener::class);
        Event::listen(BookingItemRemoved::class, RecalculateBookingTotalsListener::class);
    }
}
