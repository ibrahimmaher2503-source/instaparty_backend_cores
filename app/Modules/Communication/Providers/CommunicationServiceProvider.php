<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\Listeners\OnBookingConfirmed;
use App\Modules\Communication\Application\Listeners\OnBookingModified;
use App\Modules\Communication\Application\Listeners\OnBookingSubmitted;
use App\Modules\Communication\Application\Listeners\OnDigitalDelivered;
use App\Modules\Communication\Application\Listeners\OnDigitalExpiringSoon;
use App\Modules\Communication\Application\Listeners\OnPaymentCaptured;
use App\Modules\Communication\Application\Listeners\OnRentalDeliveryScheduled;
use App\Modules\Communication\Application\Listeners\OnSalePreparationStarted;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Infrastructure\Gateways\FcmPushAdapter;
use App\Modules\Communication\Infrastructure\Gateways\MailchimpEmailAdapter;
use App\Modules\Communication\Infrastructure\Gateways\VonageSmsAdapter;
use App\Modules\Communication\Infrastructure\Gateways\WhatsAppStubAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationDispatcher::class, DispatchNotificationAction::class);

        $this->app->bind(NotificationChannel::Push->value.'_adapter', FcmPushAdapter::class);
        $this->app->bind(NotificationChannel::Sms->value.'_adapter', VonageSmsAdapter::class);
        $this->app->bind(NotificationChannel::Whatsapp->value.'_adapter', WhatsAppStubAdapter::class);
        $this->app->bind(NotificationChannel::Email->value.'_adapter', MailchimpEmailAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'communication');

        $this->registerRoutes();
        $this->registerListeners();
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
        Event::listen('App\Modules\Booking\Domain\Events\BookingSubmitted', OnBookingSubmitted::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingModified', OnBookingModified::class);
        Event::listen('App\Modules\Booking\Domain\Events\BookingConfirmed', OnBookingConfirmed::class);
        Event::listen('App\Modules\Payments\Domain\Events\PaymentCaptured', OnPaymentCaptured::class);
        Event::listen('App\Modules\Booking\Domain\Events\RentalDeliveryScheduled', OnRentalDeliveryScheduled::class);
        Event::listen('App\Modules\Booking\Domain\Events\SalePreparationStarted', OnSalePreparationStarted::class);
        Event::listen('App\Modules\Booking\Domain\Events\DigitalDelivered', OnDigitalDelivered::class);
        Event::listen('App\Modules\Booking\Domain\Events\DigitalExpiringSoon', OnDigitalExpiringSoon::class);
    }
}
