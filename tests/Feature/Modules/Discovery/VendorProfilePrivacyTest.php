<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;

/**
 * P0 — Customer API audit 2026-06-04, Step 1.3 privacy assertions for
 * endpoint 8.1 (GET /api/v1/customer/vendors/{publicId}) and the vendor
 * index. The customer-facing vendor payload must never expose commission,
 * subscription tier, bank details, wallet, owner PII, or admin internals.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->owner = User::factory()->phoneVerified()->asVendor()->create([
        'name' => 'Real Owner Name',
        'email' => 'vendor-owner-secret@example.com',
        'phone_e164' => '+201511223344',
    ]);

    $this->vendor = VendorProfile::factory()->approved()->create([
        'user_id' => $this->owner->id,
        'bank_name' => 'Secret Bank',
        'bank_account_holder' => 'Secret Holder',
        'bank_iban' => 'EG380019000500000000263180002',
        'bank_swift_bic' => 'NBEGEGCX',
        'bank_branch' => 'Benha Branch',
    ]);
});

/** Recursively collect every key present anywhere in the payload. */
function vendorPrivacyAllKeys(mixed $payload): array
{
    if (! is_array($payload)) {
        return [];
    }

    $keys = [];
    foreach ($payload as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }
        $keys = array_merge($keys, vendorPrivacyAllKeys($value));
    }

    return $keys;
}

dataset('forbidden vendor keys', [
    'bank_name',
    'bank_account_holder',
    'bank_iban',
    'bank_swift_bic',
    'bank_branch',
    'commission_rate',
    'commission_bps',
    'subscription_tier',
    'wallet_balance',
    'email',
    'phone_e164',
    'user_id',
    'preferred_locale',
    'documents',
    'compliance_events',
    'admin_notes',
]);

it('vendor profile show does not expose forbidden key', function (string $forbiddenKey): void {
    $response = $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}")
        ->assertStatus(200);

    expect(vendorPrivacyAllKeys($response->json('data')))->not->toContain($forbiddenKey);
})->with('forbidden vendor keys')->group('discovery', 'privacy');

it('vendor profile show never leaks owner PII or bank values in the raw body', function (): void {
    $response = $this->getJson("/api/v1/customer/vendors/{$this->vendor->public_id}")
        ->assertStatus(200);

    $raw = $response->getContent();

    expect($raw)
        ->not->toContain('vendor-owner-secret@example.com')
        ->not->toContain('+201511223344')
        ->not->toContain('Real Owner Name')
        ->not->toContain('EG380019000500000000263180002')
        ->not->toContain('Secret Bank');
})->group('discovery', 'privacy');

it('vendor index does not expose bank or owner fields on any row', function (): void {
    $response = $this->getJson('/api/v1/customer/vendors')
        ->assertStatus(200);

    $keys = vendorPrivacyAllKeys($response->json('data'));

    expect($keys)
        ->not->toContain('bank_iban')
        ->not->toContain('bank_name')
        ->not->toContain('email')
        ->not->toContain('phone_e164')
        ->not->toContain('user_id');
})->group('discovery', 'privacy');

it('non-approved vendor profile returns 404 (no document or status leak)', function (): void {
    $pending = VendorProfile::factory()->create();

    $this->getJson("/api/v1/customer/vendors/{$pending->public_id}")
        ->assertStatus(404);
})->group('discovery', 'privacy');
