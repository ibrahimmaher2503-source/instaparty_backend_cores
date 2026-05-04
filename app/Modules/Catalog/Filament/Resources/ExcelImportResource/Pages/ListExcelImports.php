<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ExcelImportResource\Pages;

use App\Modules\Catalog\Filament\Resources\ExcelImportResource;
use Filament\Resources\Pages\ListRecords;

class ListExcelImports extends ListRecords
{
    protected static string $resource = ExcelImportResource::class;
}
