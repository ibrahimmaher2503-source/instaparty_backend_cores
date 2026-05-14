<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\Pages;

use App\Modules\Subscriptions\Application\Actions\InvalidatePlanFeaturesCache;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function afterCreate(): void
    {
        app(InvalidatePlanFeaturesCache::class)->execute($this->record->id);
    }
}
