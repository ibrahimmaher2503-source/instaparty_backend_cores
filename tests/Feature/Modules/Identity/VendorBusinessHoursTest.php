<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
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

it('upserts 7-day business hours and returns 200', function (): void {
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    $hours = array_map(fn (int $day): array => [
        'day_of_week' => $day,
        'opens_at' => $day === 0 ? null : '09:00',
        'closes_at' => $day === 0 ? null : '18:00',
    ], range(0, 6));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/vendor/business-hours', ['hours' => $hours])
        ->assertStatus(200)
        ->assertJsonStructure(['data' => ['hours']]);

    expect(VendorBusinessHour::where('vendor_profile_id', $vp->id)->count())->toBe(7);
})->group('identity', 'us4');

it('second upsert updates existing rows without creating duplicates', function (): void {
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    $payload = fn (string $opens) => ['hours' => [
        ['day_of_week' => 1, 'opens_at' => $opens, 'closes_at' => '20:00'],
    ]];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/vendor/business-hours', $payload('08:00'))
        ->assertStatus(200);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/vendor/business-hours', $payload('10:00'))
        ->assertStatus(200);

    expect(VendorBusinessHour::where('vendor_profile_id', $vp->id)->where('day_of_week', 1)->count())->toBe(1);

    $row = VendorBusinessHour::where('vendor_profile_id', $vp->id)->where('day_of_week', 1)->first();
    expect($row->opens_at)->toStartWith('10:00');
})->group('identity', 'us4');

it('rejects invalid opens_at format with 422', function (): void {
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/vendor/business-hours', [
            'hours' => [
                ['day_of_week' => 1, 'opens_at' => 'not-a-time', 'closes_at' => '18:00'],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hours.0.opens_at']);
})->group('identity', 'us4');

it('returns 401 when unauthenticated on business-hours endpoint', function (): void {
    $this->putJson('/api/v1/vendor/business-hours', ['hours' => []])
        ->assertStatus(401);
})->group('identity', 'us4');
