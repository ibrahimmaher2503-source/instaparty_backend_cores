<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\PaymentAttemptResource\Pages;

use App\Modules\Payments\Filament\Resources\PaymentAttemptResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentAttempts extends ListRecords
{
    protected static string $resource = PaymentAttemptResource::class;
}
