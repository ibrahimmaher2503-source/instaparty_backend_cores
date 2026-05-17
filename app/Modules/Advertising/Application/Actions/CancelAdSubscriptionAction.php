<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Application\Actions;

use App\Modules\Advertising\Domain\Enums\AdSubscriptionStatus;
use App\Modules\Advertising\Domain\Models\VendorAdSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancelAdSubscriptionAction
{
    public function execute(VendorAdSubscription $subscription, int $adminUserId): void
    {
        DB::transaction(function () use ($subscription, $adminUserId): void {
            $subscription->update(['status' => AdSubscriptionStatus::Cancelled]);

            DB::table('audit_logs')->insert([
                'public_id'      => Str::ulid(),
                'auditable_type' => VendorAdSubscription::class,
                'auditable_id'   => $subscription->id,
                'user_id'        => $adminUserId,
                'action'         => 'ad_subscription.cancelled',
                'changes'        => json_encode([
                    'status' => ['from' => AdSubscriptionStatus::Active->value, 'to' => AdSubscriptionStatus::Cancelled->value],
                ]),
                'created_at' => now(),
            ]);
        });
    }
}
