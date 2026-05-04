<?php

use App\Modules\Booking\Providers\BookingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Communication\Providers\CommunicationServiceProvider;
use App\Modules\Discovery\Providers\DiscoveryServiceProvider;
use App\Modules\Geography\Providers\GeographyServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Loyalty\Providers\LoyaltyServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Modules\Reviews\Providers\ReviewsServiceProvider;
use App\Modules\Settlement\Providers\SettlementServiceProvider;
use App\Modules\Shared\Providers\SharedServiceProvider;
use App\Modules\Subscriptions\Providers\SubscriptionsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    GeographyServiceProvider::class,
    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    DiscoveryServiceProvider::class,
    BookingServiceProvider::class,
    PaymentsServiceProvider::class,
    SettlementServiceProvider::class,
    CommunicationServiceProvider::class,
    ReviewsServiceProvider::class,
    LoyaltyServiceProvider::class,
    SubscriptionsServiceProvider::class,
    AdminPanelProvider::class,
];
