<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\AdminUpdateCustomerProfileAction;
use App\Modules\Identity\Application\Actions\ForceLogoutCustomerAction;
use App\Modules\Identity\Application\Actions\SuspendCustomerAction;
use App\Modules\Identity\Application\Actions\UnsuspendCustomerAction;
use App\Modules\Identity\Application\DTOs\AdminUpdateCustomerDTO;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// =====================================================================
// T009 / US1 — Search (basic unit test covering data presence)
// =====================================================================

it('customer scope includes customers and excludes vendors', function (): void {
    $customer = User::factory()->create(['name' => 'Sara Customer', 'email' => 'sara@example.com', 'status' => 'active']);
    $customer->assignRole('customer');

    $vendor = User::factory()->create(['name' => 'Ali Vendor', 'email' => 'ali@vendor.com', 'status' => 'active']);
    $vendor->assignRole('vendor');

    $customerIds = User::query()->customers()->pluck('id');

    expect($customerIds)->toContain($customer->id)
        ->and($customerIds)->not->toContain($vendor->id);
})->group('identity', 'customer-management');

// =====================================================================
// T021 / US2 — Admin can edit customer name and phone
// =====================================================================

it('admin can edit customer name and phone', function (): void {
    $customer = User::factory()->create(['name' => 'Old Name', 'phone_e164' => '+201001111111', 'status' => 'active']);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $this->actingAs($admin);

    $dto = new AdminUpdateCustomerDTO(name: 'New Name', phoneE164: '+201009999999');

    $updated = app(AdminUpdateCustomerProfileAction::class)->execute($customer, $dto);

    expect($updated->name)->toBe('New Name')
        ->and($updated->phone_e164)->toBe('+201009999999');

    $this->assertDatabaseHas('users', [
        'id' => $customer->id,
        'name' => 'New Name',
        'phone_e164' => '+201009999999',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id' => $customer->id,
        'description' => 'customer.profile_updated',
    ]);
})->group('identity', 'customer-management');

// =====================================================================
// T022 / US2 — DTO only includes non-null values
// =====================================================================

it('admin update dto excludes null phone', function (): void {
    $dto = new AdminUpdateCustomerDTO(name: 'Test Name');

    expect($dto->toArray())->toBe(['name' => 'Test Name'])
        ->and($dto->toArray())->not->toHaveKey('phone_e164');
})->group('identity', 'customer-management');

// =====================================================================
// T026 / US3 — Suspended customer cannot log in
// =====================================================================

it('suspended customer cannot log in', function (): void {
    $customer = User::factory()->create([
        'email' => 'suspended@test.com',
        'password' => bcrypt('password123'),
        'status' => 'active',
        'phone_e164' => '+201001234001',
    ]);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(SuspendCustomerAction::class)->execute($customer);

    $this->app['auth']->forgetGuards();

    $response = $this->postJson('/api/v1/login', [
        'login' => 'suspended@test.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['login']);
})->group('identity', 'customer-management');

// =====================================================================
// T027 / US3 — Suspended customer with existing token gets 403
// =====================================================================

it('suspended customer gets 403 on authenticated endpoint', function (): void {
    $customer = User::factory()->create(['status' => 'active', 'phone_e164' => '+201001234002']);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(SuspendCustomerAction::class)->execute($customer);

    // Manually set suspended state (tokens were revoked by the action, create a new one for the test)
    $customer->update(['status' => 'suspended']);
    $token = $customer->createToken('test-token')->plainTextToken;

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/customer/profile')
        ->assertStatus(403)
        ->assertJsonPath('errors.0.code', 'account_suspended');
})->group('identity', 'customer-management');

// =====================================================================
// T028 / US3 — Admin cannot suspend their own account
// =====================================================================

it('admin cannot suspend their own account', function (): void {
    $admin = User::factory()->create(['status' => 'active']);
    $this->actingAs($admin);

    expect(fn () => app(SuspendCustomerAction::class)->execute($admin))
        ->toThrow(DomainException::class, 'admin_cannot_self_suspend');
})->group('identity', 'customer-management');

// =====================================================================
// T029 / US3 — Suspension creates audit log entry
// =====================================================================

it('suspension creates an audit log entry', function (): void {
    $customer = User::factory()->create(['status' => 'active', 'phone_e164' => '+201001234003']);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(SuspendCustomerAction::class)->execute($customer);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id' => $customer->id,
        'description' => 'customer.suspended',
    ]);
})->group('identity', 'customer-management');

// =====================================================================
// T030 / US3 — Unsuspend restores login ability
// =====================================================================

it('unsuspend restores login ability', function (): void {
    $customer = User::factory()->create([
        'email' => 'restore@test.com',
        'password' => bcrypt('password123'),
        'status' => 'active',
        'phone_e164' => '+201001234004',
    ]);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(SuspendCustomerAction::class)->execute($customer);
    app(UnsuspendCustomerAction::class)->execute($customer);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id' => $customer->id,
        'description' => 'customer.unsuspended',
    ]);

    $this->app['auth']->forgetGuards();

    $response = $this->postJson('/api/v1/login', [
        'login' => 'restore@test.com',
        'password' => 'password123',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.token', fn ($v) => $v !== null);
})->group('identity', 'customer-management');

// =====================================================================
// T036 / US4 — Force logout revokes all tokens
// =====================================================================

it('force logout revokes all tokens', function (): void {
    $customer = User::factory()->create(['status' => 'active', 'phone_e164' => '+201001234005']);
    $customer->assignRole('customer');
    $token = $customer->createToken('device-1')->plainTextToken;

    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(ForceLogoutCustomerAction::class)->execute($customer);

    expect($customer->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/customer/profile')
        ->assertUnauthorized();
})->group('identity', 'customer-management');

// =====================================================================
// T037 / US4 — Force logout on customer with no tokens completes silently
// =====================================================================

it('force logout on customer with no tokens completes silently', function (): void {
    $customer = User::factory()->create(['status' => 'active', 'phone_e164' => '+201001234006']);
    $customer->assignRole('customer');

    expect($customer->tokens()->count())->toBe(0);

    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(ForceLogoutCustomerAction::class)->execute($customer);

    expect($customer->tokens()->count())->toBe(0);
})->group('identity', 'customer-management');

// =====================================================================
// T038 / US4 — Force logout creates audit log entry
// =====================================================================

it('force logout creates audit log entry', function (): void {
    $customer = User::factory()->create(['status' => 'active', 'phone_e164' => '+201001234007']);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(ForceLogoutCustomerAction::class)->execute($customer);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id' => $customer->id,
        'description' => 'customer.force_logout',
    ]);
})->group('identity', 'customer-management');
