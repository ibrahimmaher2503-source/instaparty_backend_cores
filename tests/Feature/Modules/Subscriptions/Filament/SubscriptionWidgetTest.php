<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Filament\Widgets\PastDueSubscriptionsStatWidget;
use App\Modules\Subscriptions\Filament\Widgets\VendorsByTierWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('vendors by tier widget shows distribution', function () {
    $freePlan = SubscriptionPlan::factory()->create(['plan_code' => 'free']);
    $silverPlan = SubscriptionPlan::factory()->create(['plan_code' => 'silver']);
    $goldPlan = SubscriptionPlan::factory()->create(['plan_code' => 'gold']);

    $freeVendors = VendorProfile::factory()->count(5)->create();
    $silverVendors = VendorProfile::factory()->count(3)->create();
    $goldVendors = VendorProfile::factory()->count(2)->create();

    foreach ($freeVendors as $vendor) {
        VendorSubscription::factory()->active()->create([
            'vendor_profile_id' => $vendor->id,
            'subscription_plan_id' => $freePlan->id,
        ]);
    }

    foreach ($silverVendors as $vendor) {
        VendorSubscription::factory()->active()->create([
            'vendor_profile_id' => $vendor->id,
            'subscription_plan_id' => $silverPlan->id,
        ]);
    }

    foreach ($goldVendors as $vendor) {
        VendorSubscription::factory()->active()->create([
            'vendor_profile_id' => $vendor->id,
            'subscription_plan_id' => $goldPlan->id,
        ]);
    }

    $widget = new VendorsByTierWidget;
    $data = $widget->getData();

    expect($data['datasets'])->toHaveLength(1);
    expect($data['datasets'][0]['data'])->toBe([5, 2, 3]);
})->group('subscriptions');

it('vendors by tier widget only counts active subscriptions', function () {
    $silverPlan = SubscriptionPlan::factory()->create(['plan_code' => 'silver']);

    $activeVendors = VendorProfile::factory()->count(2)->create();
    foreach ($activeVendors as $vendor) {
        VendorSubscription::factory()->active()->create([
            'vendor_profile_id' => $vendor->id,
            'subscription_plan_id' => $silverPlan->id,
        ]);
    }

    $cancelledVendor = VendorProfile::factory()->create();
    VendorSubscription::factory()->cancelled()->create([
        'vendor_profile_id' => $cancelledVendor->id,
        'subscription_plan_id' => $silverPlan->id,
    ]);

    $expiredVendor = VendorProfile::factory()->create();
    VendorSubscription::factory()->expired()->create([
        'vendor_profile_id' => $expiredVendor->id,
        'subscription_plan_id' => $silverPlan->id,
    ]);

    $widget = new VendorsByTierWidget;
    $data = $widget->getData();

    expect($data['datasets'][0]['data'])->toContain(2);
})->group('subscriptions');

it('past due subscriptions stat widget counts invoices', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();
    $sub = VendorSubscription::factory()->create(['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $plan->id]);

    SubscriptionInvoice::factory()
        ->count(3)
        ->create(['vendor_subscription_id' => $sub->id, 'status' => InvoiceStatus::PastDue]);
    SubscriptionInvoice::factory()
        ->count(2)
        ->create(['vendor_subscription_id' => $sub->id, 'status' => InvoiceStatus::Paid]);

    $widget = new PastDueSubscriptionsStatWidget;
    $stats = $widget->getStats();

    expect($stats)->toHaveLength(1);
    expect($stats[0]->getValue())->toBe(3);
})->group('subscriptions');

it('past due subscriptions stat widget returns zero when none', function () {
    $vendor = VendorProfile::factory()->create();
    $plan = SubscriptionPlan::factory()->create();
    VendorSubscription::factory()->create(['vendor_profile_id' => $vendor->id, 'subscription_plan_id' => $plan->id]);

    $widget = new PastDueSubscriptionsStatWidget;
    $stats = $widget->getStats();

    expect($stats[0]->getValue())->toBe(0);
})->group('subscriptions');
