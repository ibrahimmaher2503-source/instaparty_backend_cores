<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources\VendorReviewResource\Pages;

use App\Modules\Reviews\Filament\Resources\VendorReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorReviews extends ListRecords
{
    protected static string $resource = VendorReviewResource::class;
}
