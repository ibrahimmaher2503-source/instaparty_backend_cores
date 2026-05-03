<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages;

use App\Modules\Settlement\Filament\Resources\CommissionRulesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommissionRule extends CreateRecord
{
    protected static string $resource = CommissionRulesResource::class;
}
