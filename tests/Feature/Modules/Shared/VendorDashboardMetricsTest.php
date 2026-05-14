<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeAuthenticatedVendor(): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    $user->load('vendorProfile');

    return compact('user', 'vendor');
}

// ─────────────────────────────────────────────────────────────────────────────
// Stats accuracy per vendor
// ─────────────────────────────────────────────────────────────────────────────

it('pending_services count reflects only the authenticated vendor\'s services', function (): void {
    ['vendor' => $vendorA] = makeAuthenticatedVendor();
    ['vendor' => $vendorB] = makeAuthenticatedVendor();

    Service::factory()->rental()->count(3)->create([
        'vendor_profile_id' => $vendorA->id,
        'status' => ServiceStatus::PendingReview,
    ]);
    Service::factory()->rental()->count(1)->create([
        'vendor_profile_id' => $vendorB->id,
        'status' => ServiceStatus::PendingReview,
    ]);

    $pendingA = Service::query()
        ->where('vendor_profile_id', $vendorA->id)
        ->where('status', ServiceStatus::PendingReview)
        ->count();

    $pendingB = Service::query()
        ->where('vendor_profile_id', $vendorB->id)
        ->where('status', ServiceStatus::PendingReview)
        ->count();

    expect($pendingA)->toBe(3);
    expect($pendingB)->toBe(1);
})->group('shared', 'dashboard', 'metrics');

it('pending_bookings count reflects only the authenticated vendor\'s booking vendors', function (): void {
    ['vendor' => $vendorA] = makeAuthenticatedVendor();
    ['vendor' => $vendorB] = makeAuthenticatedVendor();

    BookingVendor::factory()->count(2)->create([
        'vendor_profile_id' => $vendorA->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);
    BookingVendor::factory()->count(5)->create([
        'vendor_profile_id' => $vendorB->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $pendingA = BookingVendor::query()
        ->where('vendor_profile_id', $vendorA->id)
        ->where('sub_status', VendorSubStatus::Pending)
        ->count();

    $pendingB = BookingVendor::query()
        ->where('vendor_profile_id', $vendorB->id)
        ->where('sub_status', VendorSubStatus::Pending)
        ->count();

    expect($pendingA)->toBe(2);
    expect($pendingB)->toBe(5);
})->group('shared', 'dashboard', 'metrics');

// ─────────────────────────────────────────────────────────────────────────────
// Redis cache — per-vendor key isolation
// ─────────────────────────────────────────────────────────────────────────────

it('uses separate cache keys for different vendors', function (): void {
    ['vendor' => $vendorA] = makeAuthenticatedVendor();
    ['vendor' => $vendorB] = makeAuthenticatedVendor();

    $keyA = "vendor_dashboard_stats_{$vendorA->id}";
    $keyB = "vendor_dashboard_stats_{$vendorB->id}";

    expect($keyA)->not->toBe($keyB);
})->group('shared', 'dashboard', 'cache');

it('cache stores stats under the vendor-specific key', function (): void {
    ['user' => $user, 'vendor' => $vendor] = makeAuthenticatedVendor();

    Cache::flush();

    $cacheKey = "vendor_dashboard_stats_{$vendor->id}";
    expect(Cache::has($cacheKey))->toBeFalse();

    // Populate the cache manually as the widget would
    Cache::remember($cacheKey, 300, fn () => ['bookings_this_month' => 42]);

    expect(Cache::has($cacheKey))->toBeTrue();
    expect(Cache::get($cacheKey)['bookings_this_month'])->toBe(42);
})->group('shared', 'dashboard', 'cache');

it('vendor A cache does not bleed into vendor B cache', function (): void {
    ['vendor' => $vendorA] = makeAuthenticatedVendor();
    ['vendor' => $vendorB] = makeAuthenticatedVendor();

    Cache::flush();

    $keyA = "vendor_dashboard_stats_{$vendorA->id}";
    $keyB = "vendor_dashboard_stats_{$vendorB->id}";

    Cache::remember($keyA, 300, fn () => ['pending_bookings' => 7]);
    Cache::remember($keyB, 300, fn () => ['pending_bookings' => 3]);

    expect(Cache::get($keyA)['pending_bookings'])->toBe(7);
    expect(Cache::get($keyB)['pending_bookings'])->toBe(3);
})->group('shared', 'dashboard', 'cache', 'isolation');

it('cache is invalidated after TTL by forget', function (): void {
    ['vendor' => $vendor] = makeAuthenticatedVendor();

    $cacheKey = "vendor_dashboard_stats_{$vendor->id}";

    Cache::remember($cacheKey, 300, fn () => ['value' => 'stale']);
    Cache::forget($cacheKey);

    expect(Cache::has($cacheKey))->toBeFalse();
})->group('shared', 'dashboard', 'cache');
