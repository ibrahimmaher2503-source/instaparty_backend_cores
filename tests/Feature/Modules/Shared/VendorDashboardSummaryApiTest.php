<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    // Array cache outlives RefreshDatabase — vendor IDs repeat across tests.
    Cache::flush();
});

function makeDashboardVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();

    return VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/dashboard/summary (G4)
// ─────────────────────────────────────────────────────────────────────────────

it('returns the dashboard summary shape with zeroed wallet when none exists', function (): void {
    $vendor = makeDashboardVendor();

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.wallet.available_minor', 0)
        ->assertJsonPath('data.wallet.currency', 'EGP')
        ->assertJsonStructure(['data' => [
            'action_required' => ['pending_bookings', 'next_response_deadline', 'pending_services'],
            'operations' => ['active_bookings', 'upcoming_events_7d'],
            'month_to_date' => ['bookings', 'revenue_minor', 'currency'],
            'wallet' => ['available_minor', 'pending_withdrawal_minor', 'currency'],
            'rating' => ['average'],
        ]]);
})->group('shared', 'dashboard-api');

it('counts only the authenticated vendor pending bookings', function (): void {
    $vendor = makeDashboardVendor();
    $other = makeDashboardVendor();

    BookingVendor::factory()->count(2)->create([
        'vendor_profile_id' => $vendor->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => now()->addHours(12),
    ]);
    BookingVendor::factory()->create([
        'vendor_profile_id' => $other->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.action_required.pending_bookings', 2);
})->group('shared', 'dashboard-api');

it('reports month-to-date revenue in integer minor units', function (): void {
    $vendor = makeDashboardVendor();

    BookingVendor::factory()->create([
        'vendor_profile_id' => $vendor->id,
        'sub_status' => VendorSubStatus::Accepted,
        'vendor_payout_minor' => 45000,
    ]);

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.month_to_date.revenue_minor', 45000);
})->group('shared', 'dashboard-api', 'money');

it('returns 401 when unauthenticated on dashboard summary', function (): void {
    $this->getJson('/api/v1/vendor/dashboard/summary')->assertStatus(401);
})->group('shared', 'dashboard-api', 'auth');

it('returns 403 when a customer requests the dashboard summary', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->getJson('/api/v1/vendor/dashboard/summary')
        ->assertStatus(403);
})->group('shared', 'dashboard-api', 'auth');
