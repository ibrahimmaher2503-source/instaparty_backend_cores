<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    Storage::fake('s3');
});

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/v1/vendor/booking-items/{publicId}/condition-photos (G8)
// ─────────────────────────────────────────────────────────────────────────────

it('uploads handover condition photos for a rental item', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);

    $this->actingAs($data['vendorUser'])
        ->post("/api/v1/vendor/booking-items/{$data['item']->public_id}/condition-photos", [
            'phase' => 'handover',
            'photos' => [
                UploadedFile::fake()->image('front.jpg', 800, 600),
                UploadedFile::fake()->image('back.jpg', 800, 600),
            ],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.uploaded', 2)
        ->assertJsonPath('data.phase', 'handover');

    expect($data['item']->fresh()->getMedia('condition_photos'))->toHaveCount(2)
        ->and($data['item']->fresh()->getMedia('condition_photos')->first()->getCustomProperty('phase'))->toBe('handover');
})->group('booking', 'condition-photos', 'rental');

it('uploads return-phase photos separately from handover', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);
    $url = "/api/v1/vendor/booking-items/{$data['item']->public_id}/condition-photos";

    $this->actingAs($data['vendorUser'])
        ->post($url, ['phase' => 'handover', 'photos' => [UploadedFile::fake()->image('h.jpg')]], ['Accept' => 'application/json'])
        ->assertCreated();
    $this->actingAs($data['vendorUser'])
        ->post($url, ['phase' => 'return', 'photos' => [UploadedFile::fake()->image('r.jpg')]], ['Accept' => 'application/json'])
        ->assertCreated();

    $media = $data['item']->fresh()->getMedia('condition_photos');
    expect($media->where(fn ($m) => $m->getCustomProperty('phase') === 'handover'))->toHaveCount(1)
        ->and($media->where(fn ($m) => $m->getCustomProperty('phase') === 'return'))->toHaveCount(1);
})->group('booking', 'condition-photos', 'rental');

it('writes an audit log row on upload', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);

    $this->actingAs($data['vendorUser'])
        ->post("/api/v1/vendor/booking-items/{$data['item']->public_id}/condition-photos", [
            'phase' => 'handover',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    expect(DB::table('audit_logs')
        ->where('auditable_id', $data['item']->id)
        ->where('action', 'condition_photos_uploaded')
        ->exists())->toBeTrue();
})->group('booking', 'condition-photos', 'audit');

it('rejects condition photos for non-rental items', function (ProductType $type): void {
    $data = makePaidBookingForFulfillment($type);

    $this->actingAs($data['vendorUser'])
        ->post("/api/v1/vendor/booking-items/{$data['item']->public_id}/condition-photos", [
            'phase' => 'handover',
            'photos' => [UploadedFile::fake()->image('x.jpg')],
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);
})->with([
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
])->group('booking', 'condition-photos', 'sale', 'digital');

it('returns 422 for an invalid phase', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);

    $this->actingAs($data['vendorUser'])
        ->post("/api/v1/vendor/booking-items/{$data['item']->public_id}/condition-photos", [
            'phase' => 'midway',
            'photos' => [UploadedFile::fake()->image('x.jpg')],
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phase']);
})->group('booking', 'condition-photos', 'validation');

it('returns 404 when another vendor uploads photos', function (): void {
    $data = makePaidBookingForFulfillment(ProductType::Rental);

    $other = VendorProfile::factory()->approved()->create();
    $other->user->assignRole('vendor');

    $this->actingAs($other->user)
        ->post("/api/v1/vendor/booking-items/{$data['item']->public_id}/condition-photos", [
            'phase' => 'handover',
            'photos' => [UploadedFile::fake()->image('x.jpg')],
        ], ['Accept' => 'application/json'])
        ->assertStatus(404);
})->group('booking', 'condition-photos', 'auth');
