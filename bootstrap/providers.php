<?php

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
    AdminPanelProvider::class,
];
