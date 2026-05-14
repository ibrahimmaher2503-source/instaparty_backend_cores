<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\Pages;

use App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPlans extends ListRecords
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
