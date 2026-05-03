<?php

declare(strict_types=1);

namespace App\Modules\Booking\Providers;

use App\Modules\Booking\Application\Listeners\ConfirmInventoryReservationsListener;
use App\Modules\Booking\Application\Listeners\RecalculateBookingTotalsListener;
use App\Modules\Booking\Application\Listeners\ReleaseInventoryOnCancellationListener;
use App\Modules\Booking\Application\Listeners\WriteBookingStateTransitionListener;
use App\Modules\Booking\Application\Listeners\WriteInitialBookingSnapshotListener;
use App\Modules\Booking\Application\Listeners\WriteNegotiationSnapshotListener;
use App\Modules\Booking\Console\Commands\ReleaseExpiredReservationsCommand;
use App\Modules\Booking\Domain\Contracts\BookingHistoryReader;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\BookingDraftCreated;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Events\BookingItemRemoved;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Events\CustomerModificationDecided;
use App\Modules\Booking\Domain\Events\VendorAccepted;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingHistoryReader;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingItemReviewabilityReader;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingRepository;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingVendorReviewabilityReader;
use App\Modules\Booking\Infrastructure\Repositories\EloquentPaymentsBookingReader;
use App\Modules\Booking\Infrastructure\Repositories\EloquentSettlementBookingReader;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Reviews\Domain\Contracts\BookingItemReviewabilityReader;
use App\Modules\Reviews\Domain\Contracts\BookingVendorReviewabilityReader;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookingRepository::class, EloquentBookingRepository::class);
        $this->app->bind(PaymentsBookingReader::class, EloquentPaymentsBookingReader::class);
        $this->app->bind(SettlementBookingReader::class, EloquentSettlementBookingReader::class);
        $this->app->singleton(BookingItemReviewabilityReader::class, EloquentBookingItemReviewabilityReader::class);
        $this->app->singleton(BookingVendorReviewabilityReader::class, EloquentBookingVendorReviewabilityReader::class);
        $this->app->singleton(BookingHistoryReader::class, EloquentBookingHistoryReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'booking');
        $this->loadRoutesFrom(__DIR__.'/../Routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/vendor.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        $this->commands([ReleaseExpiredReservationsCommand::class]);

        // Booking draft events (listener-driven state transition for initial draft)
        Event::listen(BookingDraftCreated::class, WriteInitialBookingSnapshotListener::class);
        Event::listen(BookingDraftCreated::class, WriteBookingStateTransitionListener::class);
        Event::listen(BookingItemAdded::class, RecalculateBookingTotalsListener::class);
        Event::listen(BookingItemRemoved::class, RecalculateBookingTotalsListener::class);

        // Negotiation loop events — snapshot only (state transitions written in actions)
        Event::listen(BookingSubmittedToVendor::class, WriteNegotiationSnapshotListener::class);
        Event::listen(VendorAccepted::class, WriteNegotiationSnapshotListener::class);
        Event::listen(VendorModificationProposed::class, WriteNegotiationSnapshotListener::class);
        Event::listen(VendorRejected::class, WriteNegotiationSnapshotListener::class);
        Event::listen(CustomerModificationDecided::class, WriteNegotiationSnapshotListener::class);
        Event::listen(BookingConfirmed::class, WriteNegotiationSnapshotListener::class);
        Event::listen(BookingConfirmed::class, ConfirmInventoryReservationsListener::class);
        Event::listen(BookingCancelled::class, WriteNegotiationSnapshotListener::class);
        Event::listen(BookingCancelled::class, ReleaseInventoryOnCancellationListener::class);
    }
}
