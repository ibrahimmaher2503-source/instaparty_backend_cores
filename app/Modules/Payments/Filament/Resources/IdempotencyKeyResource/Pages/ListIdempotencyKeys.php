<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\IdempotencyKeyResource\Pages;

use App\Modules\Payments\Filament\Resources\IdempotencyKeyResource;
use Filament\Resources\Pages\ListRecords;

class ListIdempotencyKeys extends ListRecords
{
    protected static string $resource = IdempotencyKeyResource::class;
}
