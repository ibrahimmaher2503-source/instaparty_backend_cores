<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionAuditWriter
{
    /**
     * Write both a subscription_audit row and an event_outbox row inside the current DB transaction.
     * Must be called inside DB::transaction() — never standalone.
     */
    public function write(
        VendorSubscription $subscription,
        SubscriptionEventType $eventType,
        array $beforeState = [],
        array $afterState = [],
        array $data = [],
        ?string $reason = null,
        string $actorType = 'system',
        ?int $actorId = null,
    ): SubscriptionAuditEntry {
        $entry = SubscriptionAuditEntry::create([
            'public_id'               => (string) Str::ulid(),
            'vendor_subscription_id'  => $subscription->id,
            'vendor_profile_id'       => $subscription->vendor_profile_id,
            'event_type'              => $eventType->value,
            'actor_type'              => $actorType,
            'actor_id'                => $actorId,
            'before_state'            => $beforeState ?: null,
            'after_state'             => $afterState ?: null,
            'metadata'                => $data ?: null,
            'reason'                  => $reason,
        ]);

        // Write event_outbox row (transactional outbox pattern)
        $eventId   = (string) Str::ulid();
        $dedupeHash = md5($eventType->value . ':' . $subscription->id . ':' . $entry->id);

        DB::table('event_outbox')->insertOrIgnore([
            'public_id'    => $eventId,
            'event_key'    => $eventType->value,
            'source_id'    => $subscription->id,
            'source_type'  => VendorSubscription::class,
            'dedupe_hash'  => $dedupeHash,
            'payload'      => json_encode($this->buildEnvelope($eventId, $eventType, $subscription, $data)),
            'status'       => 'pending',
            'next_retry_at' => now(),
            'created_at'   => now(),
        ]);

        return $entry;
    }

    private function buildEnvelope(
        string $eventId,
        SubscriptionEventType $eventType,
        VendorSubscription $subscription,
        array $data,
    ): array {
        return [
            'event_id'                        => $eventId,
            'event_key'                       => $eventType->value,
            'occurred_at'                     => now()->toISOString(),
            'vendor_subscription_id'          => $subscription->id,
            'vendor_subscription_public_id'   => $subscription->public_id,
            'vendor_profile_id'               => $subscription->vendor_profile_id,
            'subscription_plan_id'            => $subscription->subscription_plan_id,
            'plan_code'                       => $subscription->plan?->plan_code->value,
            'data'                            => $data,
            'version'                         => 1,
        ];
    }
}
