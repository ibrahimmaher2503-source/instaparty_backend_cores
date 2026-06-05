<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBlockedDate;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Domain\Models\VendorBankAccount;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Str;

/**
 * Vendor-portal remainder B4 — blocked dates (8.3–8.5) + bank accounts
 * (13.1–13.5 / G13). Schema additions approved 2026-06-05.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->user = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $this->user->id]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
});

it('blocks a date, lists it, and reflects it in customer availability', function (): void {
    $date = now()->addDays(5)->toDateString();

    $this->actingAs($this->user)
        ->postJson('/api/v1/vendor/availability/blocked-dates', [
            'blocked_date' => $date,
            'reason' => ['en' => 'Family holiday', 'ar' => 'إجازة عائلية'],
        ])
        ->assertStatus(201);

    $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/availability/blocked-dates')
        ->assertStatus(200)
        ->assertJsonPath('data.0.blocked_date', $date);

    // Customer-facing availability now denies the date (was always [] before).
    $this->postJson("/api/v1/customer/vendors/{$this->vendor->public_id}/availability/check", ['date' => $date])
        ->assertStatus(200)
        ->assertJsonPath('data.is_available', false);

    $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}/availability")
        ->assertStatus(200)
        ->assertJsonPath('data.blocked_dates.0', $date);
})->group('identity', 'vendor-portal', 'availability');

it('blocking the same date twice is idempotent and unblocking removes it', function (): void {
    $date = now()->addDays(3)->toDateString();

    foreach ([1, 2] as $i) {
        $this->actingAs($this->user)
            ->postJson('/api/v1/vendor/availability/blocked-dates', ['blocked_date' => $date])
            ->assertStatus(201);
    }

    expect(VendorBlockedDate::query()->where('vendor_profile_id', $this->vendor->id)->count())->toBe(1);

    $publicId = VendorBlockedDate::query()->where('vendor_profile_id', $this->vendor->id)->value('public_id');

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/vendor/availability/blocked-dates/{$publicId}")
        ->assertStatus(200);

    expect(VendorBlockedDate::query()->where('vendor_profile_id', $this->vendor->id)->count())->toBe(0);
})->group('identity', 'vendor-portal', 'availability');

it('rejects past blocked dates', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/v1/vendor/availability/blocked-dates', ['blocked_date' => now()->subDay()->toDateString()])
        ->assertStatus(422);
})->group('identity', 'vendor-portal', 'availability');

it('adds a bank account with masked IBAN; first account becomes default', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/vendor/bank-accounts', [
            'bank_name' => 'NBE',
            'account_holder' => 'ACME Events LLC',
            'iban' => 'EG380019000500000000263180002',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(201)
        ->assertJsonPath('data.is_default', true);

    expect($response->json('data.iban_masked'))->toBe('EG38*********************0002')
        ->and($response->getContent())->not->toContain('EG380019000500000000263180002');
})->group('settlement', 'vendor-portal', 'bank-accounts', 'privacy');

it('set-default moves the flag atomically', function (): void {
    $a = VendorBankAccount::query()->create([
        'public_id' => (string) Str::ulid(), 'vendor_profile_id' => $this->vendor->id,
        'bank_name' => 'A', 'account_holder' => 'H', 'iban' => 'EG11AAAAAAAAAAAAAAA', 'is_default' => true,
    ]);
    $b = VendorBankAccount::query()->create([
        'public_id' => (string) Str::ulid(), 'vendor_profile_id' => $this->vendor->id,
        'bank_name' => 'B', 'account_holder' => 'H', 'iban' => 'EG22BBBBBBBBBBBBBBB', 'is_default' => false,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/vendor/bank-accounts/{$b->public_id}/set-default")
        ->assertStatus(200);

    expect((bool) $a->refresh()->is_default)->toBeFalse()
        ->and((bool) $b->refresh()->is_default)->toBeTrue();
})->group('settlement', 'vendor-portal', 'bank-accounts');

it('cannot delete the default account while others exist', function (): void {
    $a = VendorBankAccount::query()->create([
        'public_id' => (string) Str::ulid(), 'vendor_profile_id' => $this->vendor->id,
        'bank_name' => 'A', 'account_holder' => 'H', 'iban' => 'EG11AAAAAAAAAAAAAAA', 'is_default' => true,
    ]);
    VendorBankAccount::query()->create([
        'public_id' => (string) Str::ulid(), 'vendor_profile_id' => $this->vendor->id,
        'bank_name' => 'B', 'account_holder' => 'H', 'iban' => 'EG22BBBBBBBBBBBBBBB', 'is_default' => false,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/vendor/bank-accounts/{$a->public_id}")
        ->assertStatus(422);
})->group('settlement', 'vendor-portal', 'bank-accounts');

it('cross-vendor bank account access is 404 and customer tokens get 403', function (): void {
    $foreignVendor = VendorProfile::factory()->approved()->create();
    $foreign = VendorBankAccount::query()->create([
        'public_id' => (string) Str::ulid(), 'vendor_profile_id' => $foreignVendor->id,
        'bank_name' => 'X', 'account_holder' => 'Y', 'iban' => 'EG99XXXXXXXXXXXXXXX', 'is_default' => true,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/vendor/bank-accounts/{$foreign->public_id}", ['bank_name' => 'Z'])
        ->assertStatus(404);

    $customer = User::factory()->asCustomer()->create();
    $this->actingAs($customer)
        ->getJson('/api/v1/vendor/bank-accounts')
        ->assertStatus(403);
})->group('settlement', 'vendor-portal', 'bank-accounts', 'isolation');
