<?php

declare(strict_types=1);

arch('Identity models live only in Domain/Models')
    ->expect('App\Modules\Identity\Domain\Models')
    ->toBeClasses()
    ->group('identity', 'arch');

arch('Identity models do not use DB::transaction (no business logic)')
    ->expect('App\Modules\Identity\Domain\Models')
    ->not->toUse('Illuminate\Support\Facades\DB')
    ->group('identity', 'arch');

arch('Identity Actions have the Action suffix')
    ->expect('App\Modules\Identity\Application\Actions')
    ->toHaveSuffix('Action')
    ->toBeClasses()
    ->group('identity', 'arch');

arch('Identity Actions declare an execute method')
    ->expect('App\Modules\Identity\Application\Actions')
    ->toHaveMethod('execute')
    ->group('identity', 'arch');

arch('Identity Actions do not use Request directly')
    ->expect('App\Modules\Identity\Application\Actions')
    ->not->toUse('Illuminate\Http\Request')
    ->group('identity', 'arch');

arch('Identity Controllers have the Controller suffix')
    ->expect('App\Modules\Identity\Http\Controllers')
    ->toHaveSuffix('Controller')
    ->toBeClasses()
    ->group('identity', 'arch');

arch('Identity Http Requests have the Request suffix')
    ->expect('App\Modules\Identity\Http\Requests')
    ->toHaveSuffix('Request')
    ->toExtend('Illuminate\Foundation\Http\FormRequest')
    ->group('identity', 'arch');

arch('Identity Enums are backed enums')
    ->expect('App\Modules\Identity\Domain\Enums')
    ->toBeEnums()
    ->group('identity', 'arch');
