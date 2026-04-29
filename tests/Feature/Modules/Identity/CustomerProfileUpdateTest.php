<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    Cache::flush();
});

it('updates customer profile fields and persists changes', function (): void {
    $user = User::factory()->create(['name' => 'Old Name', 'preferred_locale' => 'en']);
    $user->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/customer/profile', [
            'name' => 'New Name',
            'preferred_locale' => 'ar',
            'date_of_birth' => '1990-05-12',
            'gender' => 'male',
            'accepts_marketing' => false,
        ])
        ->assertStatus(200);

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->preferred_locale)->toBe('ar');

    $profile = CustomerProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    expect($profile->gender)->toBe('male');
    expect($profile->accepts_marketing)->toBeFalse();
})->group('identity', 'us1');

it('rejects invalid gender with 422', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/customer/profile', ['gender' => 'invalid'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gender']);
})->group('identity', 'us1');

it('rejects future date_of_birth with 422', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/customer/profile', ['date_of_birth' => now()->addYear()->toDateString()])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date_of_birth']);
})->group('identity', 'us1');

it('returns 401 when updating profile without a token', function (): void {
    $this->putJson('/api/v1/customer/profile', ['name' => 'X'])->assertStatus(401);
})->group('identity', 'us1');
