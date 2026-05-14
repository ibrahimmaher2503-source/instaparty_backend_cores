<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\Actions\ApplyAdminTierOverrideAction;
use App\Modules\Subscriptions\Application\Actions\RevokeAdminTierOverrideAction;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies admin tier override with bilingual reason', function () {
    $vendor = VendorProfile::factory()->create();
    $silverPlan = SubscriptionPlan::factory()->create(['plan_code' => 'silver']);
    $goldPlan = SubscriptionPlan::factory()->create(['plan_code' => 'gold']);

    $sub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $silverPlan->id,
        'is_admin_override' => false,
    ]);

    $reasonEn = 'Upgrade for high-volume event';
    $reasonAr = 'ترقية لحدث بحجم كبير';
    $expiresAt = Carbon::now()->addDays(30);

    $action = app(ApplyAdminTierOverrideAction::class);
    $override = $action->execute($vendor->id, $goldPlan, $reasonEn, $reasonAr, $expiresAt);

    expect($override)->toBeInstanceOf(VendorSubscription::class);
    expect($override->subscription_plan_id)->toBe($goldPlan->id);
    expect($override->is_admin_override)->toBeTrue();
    expect($override->override_expires_at?->format('Y-m-d H:i:s'))->toBe($expiresAt->format('Y-m-d H:i:s'));

    $audit = SubscriptionAuditEntry::query()
        ->where('vendor_subscription_id', $override->id)
        ->where('event_type', SubscriptionEventType::AdminOverrideApplied)
        ->first();

    expect($audit)->not->toBeNull();
    $reasonData = json_decode($audit->reason, true);
    expect($reasonData['en'])->toBe($reasonEn);
    expect($reasonData['ar'])->toBe($reasonAr);
})->group('subscriptions');

it('revokes admin tier override', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    $override = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
        'is_admin_override' => true,
        'override_expires_at' => Carbon::now()->addDays(7),
    ]);

    $action = app(RevokeAdminTierOverrideAction::class);
    $action->execute($override);

    $override->refresh();

    expect($override->status::$name)->toBe('cancelled');

    $audit = SubscriptionAuditEntry::query()
        ->where('vendor_subscription_id', $override->id)
        ->where('event_type', SubscriptionEventType::AdminOverrideEnded)
        ->first();

    expect($audit)->not->toBeNull();
})->group('subscriptions');

it('supersedes previous override when applying new one', function () {
    $vendor = VendorProfile::factory()->create();
    $silverPlan = SubscriptionPlan::factory()->create(['plan_code' => 'silver']);
    $goldPlan = SubscriptionPlan::factory()->create(['plan_code' => 'gold']);
    $platinumPlan = SubscriptionPlan::factory()->create(['plan_code' => 'premium']);

    $silverSub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $silverPlan->id,
    ]);

    $goldOverride = app(ApplyAdminTierOverrideAction::class)
        ->execute($vendor->id, $goldPlan, 'Gold override', 'تجاوز ذهبي', null);

    $platinumOverride = app(ApplyAdminTierOverrideAction::class)
        ->execute($vendor->id, $platinumPlan, 'Platinum override', 'تجاوز بلاتيني', null);

    $goldOverride->refresh();

    expect($goldOverride->status::$name)->toBe('superseded');
    expect($platinumOverride->subscription_plan_id)->toBe($platinumPlan->id);
    expect($platinumOverride->is_admin_override)->toBeTrue();
})->group('subscriptions');

it('refuses to revoke override that is not admin override', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();

    $regularSub = VendorSubscription::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'subscription_plan_id' => $plan->id,
        'is_admin_override' => false,
    ]);

    $action = app(RevokeAdminTierOverrideAction::class);

    expect(fn () => $action->execute($regularSub))
        ->toThrow(InvalidArgumentException::class);
})->group('subscriptions');
