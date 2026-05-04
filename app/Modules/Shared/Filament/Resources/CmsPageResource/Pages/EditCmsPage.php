<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\CmsPageResource\Pages;

use App\Modules\Shared\Filament\Resources\CmsPageResource;
use Filament\Resources\Pages\EditRecord;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;
}
