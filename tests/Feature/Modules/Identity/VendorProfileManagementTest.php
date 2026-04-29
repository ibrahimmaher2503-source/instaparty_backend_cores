<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    Cache::flush();
});

it('updates vendor profile and persists changes', function (): void {
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/vendor/profile', [
            'business_name' => ['en' => 'Updated Co', 'ar' => 'شركة محدثة'],
            'bio' => ['en' => 'We do events', 'ar' => 'نعمل في الفعاليات'],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.business_name', 'Updated Co');

    $vp->refresh();
    expect($vp->getTranslation('business_name', 'en'))->toBe('Updated Co')
        ->and($vp->getTranslation('business_name', 'ar'))->toBe('شركة محدثة');
})->group('identity', 'us4');

it('logs audit entry when bank_iban is updated', function (): void {
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/vendor/profile', [
            'bank_iban' => 'EG000000000000000000000000001',
            'bank_name' => 'CIB',
        ])
        ->assertStatus(200);

    $log = Activity::latest()->first();
    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('updated_vendor_profile')
        ->and($log->properties['new']['bank_iban'])->toBe('EG000000000000000000000000001');
})->group('identity', 'us4');

it('returns 401 when updating vendor profile without a token', function (): void {
    $this->putJson('/api/v1/vendor/profile', ['bank_name' => 'HSBC'])
        ->assertStatus(401);
})->group('identity', 'us4');

it('returns 403 when a customer tries to update vendor profile', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/vendor/profile', ['bank_name' => 'HSBC'])
        ->assertStatus(403);
})->group('identity', 'us4');
