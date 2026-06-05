<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Str;

/**
 * Vendor-portal remainder B5 — day-PATCH hours (8.2 C), schedule range
 * (7.3 C), onboarding-status (1.4 C-lite), document delete (3.4,
 * rejected-only per the pre-existing lang contract), chat threads (11.1),
 * suspended-vendor read-only gate.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->user = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $this->user->id]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
});

it('updates a single day of business hours', function (): void {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/vendor/business-hours/days/2', ['opens_at' => '10:00', 'closes_at' => '18:00'])
        ->assertStatus(200)
        ->assertJsonPath('data.opens_at', '10:00');

    expect(VendorBusinessHour::query()
        ->where('vendor_profile_id', $this->vendor->id)
        ->where('day_of_week', 2)
        ->exists())->toBeTrue();

    $this->actingAs($this->user)
        ->patchJson('/api/v1/vendor/business-hours/days/2', ['opens_at' => '18:00', 'closes_at' => '10:00'])
        ->assertStatus(422); // closes before opens

    $this->actingAs($this->user)
        ->patchJson('/api/v1/vendor/business-hours/days/9', ['opens_at' => '10:00', 'closes_at' => '18:00'])
        ->assertStatus(404);
})->group('identity', 'vendor-portal');

it('schedule accepts an explicit calendar range', function (): void {
    $from = now()->addDays(1)->toDateString();
    $to = now()->addDays(30)->toDateString();

    $this->actingAs($this->user)
        ->getJson("/api/v1/vendor/schedule?from={$from}&to={$to}")
        ->assertStatus(200);

    $this->actingAs($this->user)
        ->getJson("/api/v1/vendor/schedule?from={$to}&to={$from}")
        ->assertStatus(422);
})->group('booking', 'vendor-portal');

it('returns the resumable onboarding checklist', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/onboarding-status')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveKeys(['is_suspended', 'items'])
        ->and($response->json('data.items.0'))->toHaveKeys(['key', 'status', 'label']);
})->group('identity', 'vendor-portal', 'onboarding');

it('deletes a rejected document but never an approved one', function (): void {
    $rejected = $this->vendor->documents()->create([
        'public_id' => (string) Str::ulid(),
        'doc_type' => 'national_id',
        'file_path' => 'docs/x.pdf',
        'file_name' => 'x.pdf',
        'status' => DocumentStatus::Rejected,
    ]);
    $approved = $this->vendor->documents()->create([
        'public_id' => (string) Str::ulid(),
        'doc_type' => 'cr',
        'file_path' => 'docs/y.pdf',
        'file_name' => 'y.pdf',
        'status' => DocumentStatus::Approved,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/vendor/documents/{$rejected->public_id}")
        ->assertStatus(200);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/vendor/documents/{$approved->public_id}")
        ->assertStatus(422);

    expect($this->vendor->documents()->count())->toBe(1);
})->group('identity', 'vendor-portal', 'compliance');

it('lists own chat threads only', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $vendorUser = User::query()->findOrFail($data['vendor']->user_id);

    ChatThread::query()->create([
        'public_id' => (string) Str::ulid(),
        'firestore_thread_id' => 'fs-thread-1',
        'customer_id' => $data['customer']->id,
        'vendor_profile_id' => $data['vendor']->id,
        'booking_id' => $data['booking']->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($vendorUser)
        ->getJson('/api/v1/vendor/chat/threads')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.firestore_thread_id'))->toBe('fs-thread-1');

    $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/chat/threads')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
})->group('communication', 'vendor-portal', 'isolation');

it('suspended vendors are read-only: GETs pass, mutations get 403 bilingual', function (): void {
    $this->vendor->update(['approval_status' => SuspendedState::class]);

    // Reads still work (wallet/bookings visibility per the portal spec).
    $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/profile')
        ->assertStatus(200);

    // Mutations are blocked with the localized suspension error.
    $this->actingAs($this->user)
        ->postJson('/api/v1/vendor/availability/blocked-dates', ['blocked_date' => now()->addDay()->toDateString()])
        ->assertStatus(403)
        ->assertJsonPath('errors.0.code', 'vendor_suspended');

    $ar = $this->actingAs($this->user)
        ->postJson('/api/v1/vendor/services/rental', [], ['Accept-Language' => 'ar'])
        ->assertStatus(403);

    expect($ar->json('errors.0.message'))->toContain('موقوف');
})->group('identity', 'vendor-portal', 'suspension', 'authorization');
