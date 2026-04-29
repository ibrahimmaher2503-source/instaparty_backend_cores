<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorDocuments extends ListRecords
{
    protected static string $resource = VendorDocumentFilamentResource::class;
}
