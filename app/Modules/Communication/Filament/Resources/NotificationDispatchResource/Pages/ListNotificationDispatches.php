<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\NotificationDispatchResource\Pages;

use App\Modules\Communication\Filament\Resources\NotificationDispatchResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationDispatches extends ListRecords
{
    protected static string $resource = NotificationDispatchResource::class;
}
