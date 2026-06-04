<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\UserDevice;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/v1/devices — register FCM token (G12)
// ─────────────────────────────────────────────────────────────────────────────

it('registers a device for any platform', function (string $platform): void {
    $user = User::factory()->phoneVerified()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->postJson('/api/v1/devices', [
            'platform' => $platform,
            'fcm_token' => "fcm-token-{$platform}-001",
            'device_id' => 'device-abc',
        ])
        ->assertCreated()
        ->assertJsonPath('data.platform', $platform)
        ->assertJsonPath('data.fcm_token', "fcm-token-{$platform}-001");

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'platform' => $platform,
        'fcm_token' => "fcm-token-{$platform}-001",
    ]);
})->with(['ios', 'android', 'web'])->group('identity', 'devices');

it('vendors can register devices too', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $vendor->user->assignRole('vendor');

    $this->actingAs($vendor->user)
        ->postJson('/api/v1/devices', [
            'platform' => 'android',
            'fcm_token' => 'vendor-fcm-001',
        ])
        ->assertCreated();
})->group('identity', 'devices');

it('re-registering the same token is idempotent and refreshes metadata', function (): void {
    $user = User::factory()->phoneVerified()->create();
    $user->assignRole('customer');

    $this->actingAs($user)->postJson('/api/v1/devices', [
        'platform' => 'ios',
        'fcm_token' => 'fcm-repeat-001',
    ])->assertCreated();

    $this->actingAs($user)->postJson('/api/v1/devices', [
        'platform' => 'android', // device migrated / metadata changed
        'fcm_token' => 'fcm-repeat-001',
    ])->assertCreated();

    expect(UserDevice::where('fcm_token', 'fcm-repeat-001')->count())->toBe(1)
        ->and(UserDevice::where('fcm_token', 'fcm-repeat-001')->value('platform'))->toBe('android');
})->group('identity', 'devices', 'idempotency');

it('returns 422 when platform is invalid', function (): void {
    $user = User::factory()->phoneVerified()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->postJson('/api/v1/devices', ['platform' => 'windows', 'fcm_token' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['platform']);
})->group('identity', 'devices', 'validation');

it('returns 422 when fcm_token is missing', function (): void {
    $user = User::factory()->phoneVerified()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->postJson('/api/v1/devices', ['platform' => 'ios'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fcm_token']);
})->group('identity', 'devices', 'validation');

it('returns a localized Arabic validation message', function (): void {
    $user = User::factory()->phoneVerified()->create();
    $user->assignRole('customer');

    $response = $this->actingAs($user)
        ->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/devices', ['platform' => 'ios'])
        ->assertStatus(422);

    expect($response->json('errors.fcm_token.0'))->toBe(__('identity::identity.validation.fcm_token_required', locale: 'ar'));
})->group('identity', 'devices', 'locale');

it('returns 401 when registering without a token', function (): void {
    $this->postJson('/api/v1/devices', ['platform' => 'ios', 'fcm_token' => 'x'])
        ->assertStatus(401);
})->group('identity', 'devices', 'auth');

// ─────────────────────────────────────────────────────────────────────────────
// DELETE /api/v1/devices/{fcmToken} — unregister (G12)
// ─────────────────────────────────────────────────────────────────────────────

it('unregisters the user own device token', function (): void {
    $user = User::factory()->phoneVerified()->create();
    $user->assignRole('customer');
    UserDevice::factory()->create(['user_id' => $user->id, 'fcm_token' => 'fcm-del-001']);

    $this->actingAs($user)
        ->deleteJson('/api/v1/devices/fcm-del-001')
        ->assertOk();

    $this->assertDatabaseMissing('user_devices', ['fcm_token' => 'fcm-del-001']);
})->group('identity', 'devices');

it('cannot unregister another user device token', function (): void {
    $owner = User::factory()->phoneVerified()->create();
    $owner->assignRole('customer');
    UserDevice::factory()->create(['user_id' => $owner->id, 'fcm_token' => 'fcm-other-001']);

    $intruder = User::factory()->phoneVerified()->create();
    $intruder->assignRole('customer');

    $this->actingAs($intruder)
        ->deleteJson('/api/v1/devices/fcm-other-001')
        ->assertOk(); // idempotent no-op — does not leak existence

    $this->assertDatabaseHas('user_devices', ['fcm_token' => 'fcm-other-001']);
})->group('identity', 'devices', 'auth');

it('unregistering an unknown token is an idempotent no-op', function (): void {
    $user = User::factory()->phoneVerified()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->deleteJson('/api/v1/devices/never-registered')
        ->assertOk();
})->group('identity', 'devices', 'idempotency');
