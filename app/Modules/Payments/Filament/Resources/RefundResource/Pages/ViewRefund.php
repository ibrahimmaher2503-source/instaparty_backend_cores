<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\RefundResource\Pages;

use App\Modules\Payments\Filament\Resources\RefundResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRefund extends ViewRecord
{
    protected static string $resource = RefundResource::class;
}
