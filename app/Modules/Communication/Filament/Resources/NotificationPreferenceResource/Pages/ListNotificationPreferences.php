<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\NotificationPreferenceResource\Pages;

use App\Modules\Communication\Filament\Resources\NotificationPreferenceResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationPreferences extends ListRecords
{
    protected static string $resource = NotificationPreferenceResource::class;
}
