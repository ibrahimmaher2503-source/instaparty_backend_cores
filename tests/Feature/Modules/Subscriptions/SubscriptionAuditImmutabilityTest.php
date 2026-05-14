<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('prevents updating audit entry fields', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();
    $sub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $audit = SubscriptionAuditEntry::create([
        'public_id' => (string) Str::ulid(),
        'vendor_subscription_id' => $sub->id,
        'vendor_profile_id' => $vendor->id,
        'actor_type' => 'User',
        'actor_id' => 1,
        'event_type' => SubscriptionEventType::AdminOverrideApplied,
        'metadata' => ['reason_en' => 'Test'],
    ]);

    $originalEvent = $audit->event_type;

    $audit->update(['event_type' => SubscriptionEventType::Expired]);

    expect($audit->refresh()->event_type)->toBe($originalEvent);
})->group('subscriptions');

it('prevents deleting audit entries', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();
    $sub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $audit = SubscriptionAuditEntry::create([
        'public_id' => (string) Str::ulid(),
        'vendor_subscription_id' => $sub->id,
        'vendor_profile_id' => $vendor->id,
        'actor_type' => 'User',
        'actor_id' => 1,
        'event_type' => SubscriptionEventType::AdminOverrideApplied,
        'metadata' => ['reason_en' => 'Test'],
    ]);

    $id = $audit->id;

    $audit->delete();

    expect(SubscriptionAuditEntry::find($id))->not->toBeNull();
})->group('subscriptions');

it('audit entry is never soft-deleted', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();
    $sub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $audit = SubscriptionAuditEntry::create([
        'public_id' => (string) Str::ulid(),
        'vendor_subscription_id' => $sub->id,
        'vendor_profile_id' => $vendor->id,
        'actor_type' => 'User',
        'actor_id' => 1,
        'event_type' => SubscriptionEventType::AdminOverrideApplied,
        'metadata' => ['reason_en' => 'Test'],
    ]);

    expect($audit->getAttribute('deleted_at'))->toBeNull();
})->group('subscriptions');

it('stores complete metadata on audit creation', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();
    $sub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $metadata = [
        'reason_en' => 'Seasonal promotion',
        'reason_ar' => 'حملة موسمية',
        'applied_at' => now()->toIso8601String(),
    ];

    $audit = SubscriptionAuditEntry::create([
        'vendor_subscription_id' => $sub->id,
        'vendor_profile_id' => $vendor->id,
        'actor_type' => 'User',
        'actor_id' => 1,
        'event_type' => SubscriptionEventType::AdminOverrideApplied,
        'metadata' => $metadata,
    ]);

    expect($audit->metadata)->toBe($metadata);
})->group('subscriptions');
