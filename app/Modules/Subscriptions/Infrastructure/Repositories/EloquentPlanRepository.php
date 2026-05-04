<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Repositories;

use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

class EloquentPlanRepository
{
    public function findByCode(PlanCode $code): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('plan_code', $code->value)->with('features')->first();
    }

    public function findDefault(): ?SubscriptionPlan
    {
        return SubscriptionPlan::default()->with('features')->first();
    }

    public function listPublished(): Collection
    {
        return SubscriptionPlan::published()->with('features')->ordered()->get();
    }
}
