<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\PaymentResource\Pages;

use App\Modules\Payments\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;
}
