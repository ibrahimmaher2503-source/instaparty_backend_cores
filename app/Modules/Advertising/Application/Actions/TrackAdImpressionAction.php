<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Application\Actions;

use App\Modules\Advertising\Domain\Models\VendorAdSubscription;
use Illuminate\Support\Facades\DB;

class TrackAdImpressionAction
{
    public function execute(VendorAdSubscription $subscription, string $pageContext = ''): void
    {
        DB::transaction(function () use ($subscription, $pageContext): void {
            DB::table('analytics_events')->insert([
                'event_type'  => 'ad_impression',
                'payload'     => json_encode([
                    'vendor_ad_subscription_id' => $subscription->id,
                    'placement_type'            => $subscription->package->placement_type->value,
                    'page_context'              => $pageContext,
                ]),
                'created_at'  => now(),
            ]);

            DB::table('vendor_ad_subscriptions')
                ->where('id', $subscription->id)
                ->increment('impression_count');
        });
    }
}
