<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Filament\Resources\ReviewModerationLogResource\Pages;

use App\Modules\Reviews\Filament\Resources\ReviewModerationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListReviewModerationLogs extends ListRecords
{
    protected static string $resource = ReviewModerationLogResource::class;
}
