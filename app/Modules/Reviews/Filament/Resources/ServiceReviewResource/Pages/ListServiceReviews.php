<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources\ServiceReviewResource\Pages;

use App\Modules\Reviews\Filament\Resources\ServiceReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceReviews extends ListRecords
{
    protected static string $resource = ServiceReviewResource::class;
}
