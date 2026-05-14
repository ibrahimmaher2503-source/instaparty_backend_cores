<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Application\Actions\RespondToReviewAction;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewResponse;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Happy path — service review response
// ─────────────────────────────────────────────────────────────────────────────

it('creates a response to a service review with pending_moderation status', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $review = ServiceReview::factory()->approved()->create();

    $response = app(RespondToReviewAction::class)->execute(
        reviewId: $review->id,
        reviewType: ReviewType::Service,
        vendorProfile: $vendor,
        body: 'Thank you for your kind words!',
        locale: 'en',
    );

    expect($response)->toBeInstanceOf(ReviewResponse::class);
    expect($response->moderation_status)->toBe(ModerationStatus::Pending);
    expect($response->body)->toBe('Thank you for your kind words!');
    expect($response->vendor_profile_id)->toBe($vendor->id);
    expect($response->review_id)->toBe($review->id);
    expect($response->review_type)->toBe(ReviewType::Service);
})->group('reviews', 'vendor', 'respond');

// ─────────────────────────────────────────────────────────────────────────────
// Happy path — vendor review response
// ─────────────────────────────────────────────────────────────────────────────

it('creates a response to a vendor review with pending_moderation status', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $review = VendorReview::factory()->approved()->create(['vendor_profile_id' => $vendor->id]);

    $response = app(RespondToReviewAction::class)->execute(
        reviewId: $review->id,
        reviewType: ReviewType::Vendor,
        vendorProfile: $vendor,
        body: 'شكراً لتقييمك!',
        locale: 'ar',
    );

    expect($response->review_type)->toBe(ReviewType::Vendor);
    expect($response->moderation_status)->toBe(ModerationStatus::Pending);
    expect($response->locale->value)->toBe('ar');
})->group('reviews', 'vendor', 'respond');

// ─────────────────────────────────────────────────────────────────────────────
// Empty body guard
// ─────────────────────────────────────────────────────────────────────────────

it('throws ValidationException when response body is empty', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $review = ServiceReview::factory()->approved()->create();

    expect(fn () => app(RespondToReviewAction::class)->execute(
        reviewId: $review->id,
        reviewType: ReviewType::Service,
        vendorProfile: $vendor,
        body: '',
    ))->toThrow(ValidationException::class);
})->group('reviews', 'vendor', 'respond', 'validation');

it('throws ValidationException when response body is whitespace only', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $review = ServiceReview::factory()->approved()->create();

    expect(fn () => app(RespondToReviewAction::class)->execute(
        reviewId: $review->id,
        reviewType: ReviewType::Service,
        vendorProfile: $vendor,
        body: '   ',
    ))->toThrow(ValidationException::class);
})->group('reviews', 'vendor', 'respond', 'validation');

// ─────────────────────────────────────────────────────────────────────────────
// Cross-vendor isolation — each vendor's response is scoped to their profile
// ─────────────────────────────────────────────────────────────────────────────

it('allows different vendors to respond to the same review independently', function (): void {
    $vendor1 = VendorProfile::factory()->approved()->create();
    $vendor2 = VendorProfile::factory()->approved()->create();
    $review = ServiceReview::factory()->approved()->create();

    app(RespondToReviewAction::class)->execute($review->id, ReviewType::Service, $vendor1, 'Response from vendor 1');
    app(RespondToReviewAction::class)->execute($review->id, ReviewType::Service, $vendor2, 'Response from vendor 2');

    expect(ReviewResponse::where('vendor_profile_id', $vendor1->id)->count())->toBe(1);
    expect(ReviewResponse::where('vendor_profile_id', $vendor2->id)->count())->toBe(1);
})->group('reviews', 'vendor', 'respond', 'isolation');

// ─────────────────────────────────────────────────────────────────────────────
// Response reset to pending on update
// ─────────────────────────────────────────────────────────────────────────────

it('resets moderation_status to pending when vendor updates their response', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $review = ServiceReview::factory()->approved()->create();

    // First response — simulate that it got approved
    $first = app(RespondToReviewAction::class)->execute(
        $review->id, ReviewType::Service, $vendor, 'Original response'
    );
    $first->update(['moderation_status' => ModerationStatus::Approved]);

    // Vendor updates the response
    $updated = app(RespondToReviewAction::class)->execute(
        $review->id, ReviewType::Service, $vendor, 'Updated response'
    );

    expect($updated->moderation_status)->toBe(ModerationStatus::Pending);
    expect($updated->body)->toBe('Updated response');
    expect($updated->id)->toBe($first->id); // same record (updateOrCreate)
})->group('reviews', 'vendor', 'respond');
