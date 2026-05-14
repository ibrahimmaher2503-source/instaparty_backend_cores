<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Events\VendorApproved;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Subscriptions\Application\Listeners\OnVendorRegistered;
use App\Modules\Subscriptions\Database\Seeders\SubscriptionPlansSeeder;
use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    $this->seed(SubscriptionPlansSeeder::class);
});

it('auto-enrols a vendor on the Free tier when VendorRegistered fires', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    app(OnVendorRegistered::class)->handle(new VendorRegistered($vendor));

    $sub = VendorSubscription::where('vendor_profile_id', $vendor->id)->first();
    expect($sub)->not->toBeNull()
        ->and((string) $sub->status)->toBe(SubscriptionStatus::Active->value)
        ->and($sub->plan->plan_code->value)->toBe(PlanCode::Free->value);

    expect(SubscriptionAuditEntry::where('vendor_subscription_id', $sub->id)->count())->toBe(1);
})->group('subscriptions', 'us1');

it('is idempotent when the registration event is replayed', function (): void {
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    $listener = app(OnVendorRegistered::class);
    $listener->handle(new VendorRegistered($vendor));
    $listener->handle(new VendorRegistered($vendor));
    $listener->handle(new VendorApproved($vendor));

    expect(VendorSubscription::where('vendor_profile_id', $vendor->id)->count())->toBe(1);
})->group('subscriptions', 'us1');
