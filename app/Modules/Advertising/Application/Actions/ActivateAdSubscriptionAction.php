<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Application\Actions;

use App\Modules\Advertising\Domain\Enums\AdSubscriptionStatus;
use App\Modules\Advertising\Domain\Models\VendorAdSubscription;
use Illuminate\Support\Facades\DB;

class ActivateAdSubscriptionAction
{
    public function execute(VendorAdSubscription $subscription): VendorAdSubscription
    {
        return DB::transaction(function () use ($subscription): VendorAdSubscription {
            $package   = $subscription->package;
            $startsAt  = now();
            $endsAt    = $startsAt->copy()->addDays($package->duration_days);

            $subscription->update([
                'status'    => AdSubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
            ]);

            return $subscription->fresh();
        });
    }
}
