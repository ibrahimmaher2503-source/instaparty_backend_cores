<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeInboxVendor(): VendorProfile
{
    $vendor = VendorProfile::factory()->approved()->create();
    $vendor->user->assignRole('vendor');

    return $vendor;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/reviews — list (G9)
// ─────────────────────────────────────────────────────────────────────────────

it('lists approved service reviews for the vendor own services only', function (): void {
    $vendor = makeInboxVendor();
    $service = Service::factory()->create(['vendor_profile_id' => $vendor->id]);

    ServiceReview::factory()->approved()->count(2)->create(['service_id' => $service->id]);
    ServiceReview::factory()->approved()->create(); // other vendor's service
    ServiceReview::factory()->create(['service_id' => $service->id]); // pending moderation — hidden

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/reviews?type=service')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.review_type', 'service');
})->group('reviews', 'vendor-inbox');

it('lists approved vendor reviews for the vendor', function (): void {
    $vendor = makeInboxVendor();

    VendorReview::factory()->approved()->count(2)->create(['vendor_profile_id' => $vendor->id]);
    VendorReview::factory()->approved()->create(); // other vendor

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/reviews?type=vendor')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.review_type', 'vendor');
})->group('reviews', 'vendor-inbox');

it('filters reviews by response status', function (): void {
    $vendor = makeInboxVendor();

    $unanswered = VendorReview::factory()->approved()->create(['vendor_profile_id' => $vendor->id]);
    $answered = VendorReview::factory()->approved()->create(['vendor_profile_id' => $vendor->id]);

    ReviewResponse::factory()->create([
        'review_type' => ReviewType::Vendor,
        'review_id' => $answered->id,
        'vendor_profile_id' => $vendor->id,
    ]);

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/reviews?type=vendor&status=new')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.public_id', $unanswered->public_id);

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/reviews?type=vendor&status=responded')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.public_id', $answered->public_id);
})->group('reviews', 'vendor-inbox');

it('returns 422 for an unknown type filter', function (): void {
    $vendor = makeInboxVendor();

    $this->actingAs($vendor->user)
        ->getJson('/api/v1/vendor/reviews?type=banana')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
})->group('reviews', 'vendor-inbox', 'validation');

it('returns 401 when listing reviews without a token', function (): void {
    $this->getJson('/api/v1/vendor/reviews')->assertStatus(401);
})->group('reviews', 'vendor-inbox', 'auth');

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/v1/vendor/reviews/{reviewType}/{publicId} — detail (G9)
// ─────────────────────────────────────────────────────────────────────────────

it('shows a service review with the vendor own response attached', function (): void {
    $vendor = makeInboxVendor();
    $service = Service::factory()->create(['vendor_profile_id' => $vendor->id]);
    $review = ServiceReview::factory()->approved()->create(['service_id' => $service->id]);

    ReviewResponse::factory()->create([
        'review_type' => ReviewType::Service,
        'review_id' => $review->id,
        'vendor_profile_id' => $vendor->id,
    ]);

    $this->actingAs($vendor->user)
        ->getJson("/api/v1/vendor/reviews/service/{$review->public_id}")
        ->assertOk()
        ->assertJsonPath('data.public_id', $review->public_id)
        ->assertJsonStructure(['data' => [
            'public_id', 'review_type', 'rating', 'body', 'reviewer_first_name',
            'service' => ['public_id', 'name', 'product_type'],
            'response' => ['public_id', 'body', 'moderation_status'],
        ]]);
})->group('reviews', 'vendor-inbox');

it('returns 404 when showing another vendor review', function (): void {
    $vendor = makeInboxVendor();
    $review = VendorReview::factory()->approved()->create(); // other vendor

    $this->actingAs($vendor->user)
        ->getJson("/api/v1/vendor/reviews/vendor/{$review->public_id}")
        ->assertStatus(404);
})->group('reviews', 'vendor-inbox', 'auth');

it('returns localized reviewer fallback in Arabic', function (): void {
    $vendor = makeInboxVendor();
    $review = VendorReview::factory()->approved()->create(['vendor_profile_id' => $vendor->id]);

    $this->actingAs($vendor->user)
        ->withHeader('Accept-Language', 'ar')
        ->getJson("/api/v1/vendor/reviews/vendor/{$review->public_id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['reviewer_first_name']]);
})->group('reviews', 'vendor-inbox', 'locale');
