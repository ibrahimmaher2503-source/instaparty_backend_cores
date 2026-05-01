<?php

use App\Modules\Booking\Providers\BookingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Discovery\Providers\DiscoveryServiceProvider;
use App\Modules\Geography\Providers\GeographyServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Shared\Providers\SharedServiceProvider;
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
    AdminPanelProvider::class,
];
