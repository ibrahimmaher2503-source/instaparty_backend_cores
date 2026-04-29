<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorApprovalQueue extends ListRecords
{
    protected static string $resource = VendorApprovalQueueResource::class;
}
