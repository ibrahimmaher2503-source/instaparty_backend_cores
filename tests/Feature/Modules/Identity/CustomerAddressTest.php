<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    Cache::flush();
});

function authenticatedCustomer(): array
{
    $user = User::factory()->create();
    $user->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

it('creates a customer address and returns 201 with public_id', function (): void {
    [$user, $token] = authenticatedCustomer();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/customer/addresses', [
            'city_id' => 1,
            'label' => 'Home',
            'address_line' => '123 Tahrir Square',
            'recipient_name' => 'Ibrahim',
            'recipient_phone_e164' => '+201001234567',
        ])
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'city_id', 'label', 'is_default']]);

    expect(CustomerAddress::where('user_id', $user->id)->count())->toBe(1);
})->group('identity', 'us4');

it('soft-deletes an address — row has deleted_at after DELETE request', function (): void {
    [$user, $token] = authenticatedCustomer();

    $address = CustomerAddress::create([
        'user_id' => $user->id,
        'city_id' => 1,
        'label' => 'Work',
        'address_line' => '456 Tahrir',
        'recipient_name' => 'Ibrahim',
        'recipient_phone_e164' => '+201001234567',
        'is_default' => false,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/customer/addresses/{$address->public_id}")
        ->assertStatus(204);

    expect(CustomerAddress::withTrashed()->find($address->id)->deleted_at)->not->toBeNull();
    expect(CustomerAddress::find($address->id))->toBeNull();
})->group('identity', 'us4');

it('rejects address with nonexistent city_id with 422', function (): void {
    [, $token] = authenticatedCustomer();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/customer/addresses', [
            'city_id' => 99999,
            'label' => 'Home',
            'address_line' => '123 Street',
            'recipient_name' => 'Ibrahim',
            'recipient_phone_e164' => '+201001234567',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['city_id']);
})->group('identity', 'us4');

it('sets is_default=true and clears other defaults', function (): void {
    [$user, $token] = authenticatedCustomer();

    $existing = CustomerAddress::create([
        'user_id' => $user->id,
        'city_id' => 1,
        'label' => 'Old Default',
        'address_line' => '1 Street',
        'recipient_name' => 'Ibrahim',
        'recipient_phone_e164' => '+201001234567',
        'is_default' => true,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/customer/addresses', [
            'city_id' => 1,
            'label' => 'New Default',
            'address_line' => '2 Street',
            'recipient_name' => 'Ibrahim',
            'recipient_phone_e164' => '+201001234567',
            'is_default' => true,
        ])
        ->assertStatus(201);

    expect($existing->fresh()->is_default)->toBeFalse();
    expect(CustomerAddress::where('user_id', $user->id)->where('is_default', true)->count())->toBe(1);
})->group('identity', 'us4');

it('returns 403 when customer tries to delete another user address', function (): void {
    [$userA, $tokenA] = authenticatedCustomer();
    [$userB] = authenticatedCustomer();

    $address = CustomerAddress::create([
        'user_id' => $userB->id,
        'city_id' => 1,
        'label' => 'Home',
        'address_line' => '1 Street',
        'recipient_name' => 'B User',
        'recipient_phone_e164' => '+201009999999',
        'is_default' => false,
    ]);

    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->deleteJson("/api/v1/customer/addresses/{$address->public_id}")
        ->assertStatus(403);
})->group('identity', 'us4');

it('returns 401 when unauthenticated on address endpoint', function (): void {
    $this->postJson('/api/v1/customer/addresses', [])->assertStatus(401);
})->group('identity', 'us4');
