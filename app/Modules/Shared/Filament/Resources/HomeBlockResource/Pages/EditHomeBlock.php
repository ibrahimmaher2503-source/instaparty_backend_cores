<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\HomeBlockResource\Pages;

use App\Modules\Shared\Domain\Models\HomeBlock;
use App\Modules\Shared\Filament\Resources\HomeBlockResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditHomeBlock extends EditRecord
{
    protected static string $resource = HomeBlockResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var HomeBlock $record */
        return HomeBlockResource::persistViaAction($data, $record);
    }
}
