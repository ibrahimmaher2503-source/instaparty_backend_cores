<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    Cache::flush();
    Storage::fake('s3-private');
});

function authenticatedVendor(): array
{
    $vp = VendorProfile::factory()->create();
    $token = $vp->user->createToken('test')->plainTextToken;

    return [$vp, $token];
}

it('uploads a PDF document and returns 201 with pending status', function (): void {
    [$vp, $token] = authenticatedVendor();
    $file = UploadedFile::fake()->create('cr.pdf', 200, 'application/pdf');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/documents', [
            'doc_type' => 'cr',
            'file' => $file,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.doc_type', 'cr')
        ->assertJsonPath('data.file_name', 'cr.pdf')
        ->assertJsonPath('data.status', 'pending');

    $doc = VendorDocument::query()->where('vendor_profile_id', $vp->id)->first();
    expect($doc)->not->toBeNull()
        ->and($doc->status)->toBe(DocumentStatus::Pending)
        ->and($doc->file_path)->toStartWith("vendors/{$vp->id}/documents/");

    Storage::disk('s3-private')->assertExists($doc->file_path);
})->group('identity', 'us2');

it('uploads a JPEG document and returns 201', function (): void {
    [$vp, $token] = authenticatedVendor();
    $file = UploadedFile::fake()->image('id.jpg');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/documents', [
            'doc_type' => 'national_id',
            'file' => $file,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.doc_type', 'national_id');
})->group('identity', 'us2');

it('rejects file >10 MB with 422', function (): void {
    [, $token] = authenticatedVendor();
    $file = UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/documents', [
            'doc_type' => 'cr',
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
})->group('identity', 'us2');

it('rejects invalid MIME (e.g. gif) with 422', function (): void {
    [, $token] = authenticatedVendor();
    $file = UploadedFile::fake()->create('bad.gif', 100, 'image/gif');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/documents', [
            'doc_type' => 'cr',
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
})->group('identity', 'us2');

it('rejects invalid doc_type with 422', function (): void {
    [, $token] = authenticatedVendor();
    $file = UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/documents', [
            'doc_type' => 'fake_type',
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['doc_type']);
})->group('identity', 'us2');

it('returns 401 when uploading without token', function (): void {
    $this->postJson('/api/v1/vendor/documents', [
        'doc_type' => 'cr',
        'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
    ])->assertStatus(401);
})->group('identity', 'us2');

it('returns 403 when a customer tries to upload', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/vendor/documents', [
            'doc_type' => 'cr',
            'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(403);
})->group('identity', 'us2');
