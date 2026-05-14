<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Actions;

use App\Modules\Subscriptions\Application\Services\SubscriptionAuditWriter;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Events\AdminOverrideEnded;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RevokeAdminTierOverrideAction
{
    public function __construct(
        private SubscriptionAuditWriter $auditWriter,
    ) {}

    public function execute(VendorSubscription $override): void
    {
        if (! $override->is_admin_override) {
            throw new InvalidArgumentException('Can only revoke admin overrides.');
        }

        DB::transaction(function () use ($override) {
            $beforeState = [
                'status' => $override->status::$name,
                'plan_code' => $override->plan?->plan_code?->value,
            ];

            $override->update(['status' => SubscriptionStatus::Cancelled->value, 'ended_at' => now()]);

            $this->auditWriter->write(
                $override,
                SubscriptionEventType::AdminOverrideEnded,
                beforeState: $beforeState,
                afterState: ['status' => SubscriptionStatus::Cancelled->value],
                actorType: 'admin',
                actorId: auth()->id(),
            );

            DB::afterCommit(fn () => event(new AdminOverrideEnded(
                $override,
                $override->plan?->plan_code->value ?? '',
                '', // underlyingPlanCode would be the previous plan, but we may not have it
                'Admin revoked override'
            )));
        });
    }
}
