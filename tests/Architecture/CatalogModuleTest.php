<?php

declare(strict_types=1);

arch('Catalog Actions have a single public execute method')
    ->expect('App\Modules\Catalog\Application\Actions')
    ->toHaveMethod('execute')
    ->not->toHavePublicMethodsBesides(['execute', '__construct'])
    ->group('catalog', 'arch');

arch('Catalog Domain Models do not use DB facade directly')
    ->expect('App\Modules\Catalog\Domain\Models')
    ->not->toUse(['Illuminate\Support\Facades\DB'])
    ->group('catalog', 'arch');

arch('Catalog module does not import Identity Eloquent models directly')
    ->expect('App\Modules\Catalog\Application')
    ->not->toUse('App\Modules\Identity\Domain\Models\VendorProfile')
    ->not->toUse('App\Modules\Identity\Domain\Models\VendorApprovedProductType')
    ->not->toUse('App\Modules\Identity\Domain\Models\User')
    ->group('catalog', 'arch');
