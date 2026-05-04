<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\AdminInboxResource\Pages;

use App\Modules\Communication\Filament\Resources\AdminInboxResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminInboxItems extends ListRecords
{
    protected static string $resource = AdminInboxResource::class;
}
