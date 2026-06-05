<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Vendor-portal remainder B3 — branding (2.3–2.6, path-based) + portfolio
 * (2.9/2.10, ADR-0047 media collection).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    Storage::fake('public');
    Storage::fake('s3-public');

    $this->user = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $this->user->id, 'logo_path' => null]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
});

it('uploads and removes the logo', function (): void {
    $this->actingAs($this->user)
        ->post('/api/v1/vendor/profile/logo', ['file' => UploadedFile::fake()->image('logo.png', 300, 300)])
        ->assertStatus(201);

    $path = $this->vendor->refresh()->logo_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->user)
        ->deleteJson('/api/v1/vendor/profile/logo')
        ->assertStatus(200);

    expect($this->vendor->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
})->group('identity', 'vendor-portal', 'media');

it('replacing the cover deletes the previous file', function (): void {
    $this->actingAs($this->user)
        ->post('/api/v1/vendor/profile/cover', ['file' => UploadedFile::fake()->image('a.jpg', 1200, 400)])
        ->assertStatus(201);
    $first = $this->vendor->refresh()->cover_path;

    $this->actingAs($this->user)
        ->post('/api/v1/vendor/profile/cover', ['file' => UploadedFile::fake()->image('b.jpg', 1200, 400)])
        ->assertStatus(201);

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($this->vendor->refresh()->cover_path);
})->group('identity', 'vendor-portal', 'media');

it('rejects non-image branding uploads', function (): void {
    $this->actingAs($this->user)
        ->post('/api/v1/vendor/profile/logo', ['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertStatus(422);
})->group('identity', 'vendor-portal', 'media');

it('uploads, lists and deletes portfolio photos', function (): void {
    $this->actingAs($this->user)
        ->post('/api/v1/vendor/profile/portfolio', [
            'files' => [UploadedFile::fake()->image('p1.jpg', 800, 600), UploadedFile::fake()->image('p2.jpg', 800, 600)],
        ])
        ->assertStatus(201);

    $list = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/profile/portfolio')
        ->assertStatus(200);

    expect($list->json('data'))->toHaveCount(2);

    $mediaId = $list->json('data.0.public_id');
    $this->actingAs($this->user)
        ->deleteJson("/api/v1/vendor/profile/portfolio/{$mediaId}")
        ->assertStatus(200);

    expect($this->vendor->refresh()->getMedia('portfolio'))->toHaveCount(1);
})->group('identity', 'vendor-portal', 'media');

it('cannot delete another vendor portfolio photo — 404', function (): void {
    $otherUser = User::factory()->phoneVerified()->asVendor()->create();
    $other = VendorProfile::factory()->approved()->create(['user_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->post('/api/v1/vendor/profile/portfolio', ['files' => [UploadedFile::fake()->image('x.jpg')]])
        ->assertStatus(201);

    $foreignId = $other->refresh()->getMedia('portfolio')->first()->uuid;

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/vendor/profile/portfolio/{$foreignId}")
        ->assertStatus(404);

    expect($other->refresh()->getMedia('portfolio'))->toHaveCount(1);
})->group('identity', 'vendor-portal', 'media', 'isolation');
