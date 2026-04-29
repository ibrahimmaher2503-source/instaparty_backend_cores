<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    Cache::flush();
});

it('adds a coverage area with valid city_id and money columns', function (): void {
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/coverage-areas', [
            'city_id' => 1,
            'delivery_fee_minor' => 2000,
            'delivery_fee_currency' => 'EGP',
            'min_order_minor' => 50000,
            'min_order_currency' => 'EGP',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.city_id', 1)
        ->assertJsonPath('data.delivery_fee_minor', 2000)
        ->assertJsonPath('data.min_order_minor', 50000);

    expect(VendorCoverageArea::where('vendor_profile_id', $vp->id)->where('city_id', 1)->exists())->toBeTrue();
})->group('identity', 'us2');

it('rejects coverage area with nonexistent city_id (422)', function (): void {
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/coverage-areas', [
            'city_id' => 99999,
            'delivery_fee_minor' => 0,
            'min_order_minor' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['city_id']);
})->group('identity', 'us2');
