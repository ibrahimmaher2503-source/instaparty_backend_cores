<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Booking\Domain\Contracts\BookingHistoryReader;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingHistoryReader;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\Listeners\DispatchServiceChangesRequestedNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchServiceResubmittedNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchVendorChangesRequestedNotificationListener;
use App\Modules\Communication\Application\Listeners\DispatchVendorResubmittedNotificationListener;
use App\Modules\Communication\Application\Listeners\OnBookingConfirmed;
use App\Modules\Communication\Application\Listeners\OnBookingForceCancelledNotifyListener;
use App\Modules\Communication\Application\Listeners\OnBookingModified;
use App\Modules\Communication\Application\Listeners\OnBookingStalledInbox;
use App\Modules\Communication\Application\Listeners\OnBookingSubmitted;
use App\Modules\Communication\Application\Listeners\OnChatFlaggedInbox;
use App\Modules\Communication\Application\Listeners\OnPaymentCaptured;
use App\Modules\Communication\Application\Listeners\OnPaymentFailedInbox;
use App\Modules\Communication\Application\Listeners\OnServiceSubmittedForReviewInbox;
use App\Modules\Communication\Application\Listeners\OnVendorRegisteredInbox;
use App\Modules\Communication\Application\Listeners\OnWithdrawalRequestedInbox;
use App\Modules\Communication\Application\Services\SegmentResolver;
use App\Modules\Communication\Console\WakeupSnoozedInboxItemsCommand;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Infrastructure\Gateways\FcmPushAdapter;
use App\Modules\Communication\Infrastructure\Gateways\MailchimpEmailAdapter;
use App\Modules\Communication\Infrastructure\Gateways\VonageSmsAdapter;
use App\Modules\Communication\Infrastructure\Gateways\WhatsAppStubAdapter;
use App\Modules\Identity\Domain\Events\VendorProfileResubmitted;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use App\Modules\Shared\Domain\Events\ChangeRequestResubmitted;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationDispatcher::class, DispatchNotificationAction::class);
        $this->app->singleton(BookingHistoryReader::class, EloquentBookingHistoryReader::class);
        $this->app->singleton(SegmentResolver::class);

        $this->app->bind(NotificationChannel::Push->value.'_adapter', FcmPushAdapter::class);
        $this->app->bind(NotificationChannel::Sms->value.'_adapter', VonageSmsAdapter::class);
        $this->app->bind(NotificationChannel::Whatsapp->value.'_adapter', WhatsAppStubAdapter::class);
        $this->app->bind(NotificationChannel::Email->value.'_adapter', MailchimpEmailAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'communication');
        $this->commands([WakeupSnoozedInboxItemsCommand::class]);

        $this->registerRoutes();
        $this->registerListeners();
        $this->registerSchedule();
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/customer')
            ->group(__DIR__.'/../Routes/customer.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/vendor')
            ->group(__DIR__.'/../Routes/vendor.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/admin')
            ->group(__DIR__.'/../Routes/admin.php');
    }

    private function registerListeners(): void
    {
        // Existing notification listeners
        Event::listen('App\Modules\Booking\Domain\Events\BookingSubmittedToVendor', OnBookingSubmitted::class);
        Event::listen('App\Modules\Booking\Domain\Events\CustomerModificationDecided', OnBookingModified::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingConfirmed', OnBookingConfirmed::class);
        Event::listen('App\Modules\Payments\Domain\Events\PaymentCaptured', OnPaymentCaptured::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingForceCancelled', OnBookingForceCancelledNotifyListener::class);
        // OnRentalDeliveryScheduled, OnSalePreparationStarted, OnDigitalDelivered, OnDigitalExpiringSoon
        // are wired in a later phase once those lifecycle events exist in the Booking module.

        // Change request notification listeners
        Event::listen(ChangeRequestCreated::class, DispatchVendorChangesRequestedNotificationListener::class);
        Event::listen(VendorProfileResubmitted::class, DispatchVendorResubmittedNotificationListener::class);
        Event::listen(ChangeRequestCreated::class, DispatchServiceChangesRequestedNotificationListener::class);
        Event::listen(ChangeRequestResubmitted::class, DispatchServiceResubmittedNotificationListener::class);

        // Admin inbox routing listeners
        Event::listen('App\Modules\Identity\Domain\Events\VendorRegistered', OnVendorRegisteredInbox::class);
        Event::listen('App\Modules\Payments\Domain\Events\PaymentFailed', OnPaymentFailedInbox::class);
        Event::listen('App\Modules\Settlement\Domain\Events\WithdrawalRequested', OnWithdrawalRequestedInbox::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingStalled', OnBookingStalledInbox::class);
        Event::listen('App\Modules\Communication\Domain\Events\ChatFlagged', OnChatFlaggedInbox::class);
        Event::listen('App\Modules\Catalog\Domain\Events\ServiceSubmittedForReview', OnServiceSubmittedForReviewInbox::class);
    }

    private function registerSchedule(): void
    {
        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command(WakeupSnoozedInboxItemsCommand::class)
                ->everyFiveMinutes()
                ->withoutOverlapping();
        });
    }
}
