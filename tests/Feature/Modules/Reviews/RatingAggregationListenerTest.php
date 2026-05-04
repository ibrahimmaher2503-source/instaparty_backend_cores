<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reviews\Application\Actions\ModerateReviewAction;
use App\Modules\Reviews\Application\DTOs\ModerateReviewData;
use App\Modules\Reviews\Application\Listeners\RecomputeRatingOnApproval;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Events\ReviewApproved;
use App\Modules\Reviews\Domain\Events\ReviewRejected;
use App\Modules\Reviews\Domain\Events\ReviewSelfDeleted;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('updates service rating_avg and rating_count when a review is approved', function (): void {
    $data = makeCompletedBookingItem();

    // Create two approved reviews
    $review1 = ServiceReview::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $data['service']->id,
        'booking_item_id' => $data['bookingItem']->id,
        'user_id' => $data['customer']->id,
        'rating' => 4,
        'body' => null,
        'locale' => 'en',
        'moderation_status' => 'pending',
    ]);

    $admin = User::factory()->asAdmin()->create();

    app(ModerateReviewAction::class)->execute($review1, new ModerateReviewData(
        toStatus: ModerationStatus::Approved,
        moderatorId: $admin->id,
    ));

    // Run queued jobs synchronously
    Queue::fake();
    event(new ReviewApproved(
        reviewId: $review1->id,
        reviewPublicId: $review1->public_id,
        reviewType: 'service',
        subjectId: $data['service']->id,
        rating: 4,
        approvedAt: now(),
        moderatorId: $admin->id,
        previousStatus: 'pending',
    ));

    app(RecomputeRatingOnApproval::class)->handle(new ReviewApproved(
        reviewId: $review1->id,
        reviewPublicId: $review1->public_id,
        reviewType: 'service',
        subjectId: $data['service']->id,
        rating: 4,
        approvedAt: now(),
        moderatorId: $admin->id,
        previousStatus: 'pending',
    ));

    $this->assertDatabaseHas('services', [
        'id' => $data['service']->id,
        'rating_avg' => 4.0,
        'rating_count' => 1,
    ]);
})->group('reviews');

it('does not recompute rating when ReviewRejected previousStatus is not approved', function (): void {
    $data = makeCompletedBookingItem();
    $review = ServiceReview::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $data['service']->id,
        'booking_item_id' => $data['bookingItem']->id,
        'user_id' => $data['customer']->id,
        'rating' => 5,
        'locale' => 'en',
        'moderation_status' => 'pending',
    ]);

    app(RecomputeRatingOnApproval::class)->handle(new ReviewRejected(
        reviewId: $review->id,
        reviewPublicId: $review->public_id,
        reviewType: 'service',
        subjectId: $data['service']->id,
        reason: null,
        moderatorId: 1,
        rejectedAt: now(),
        previousStatus: 'pending', // not 'approved'
    ));

    // rating_count should remain 0 (no aggregation happened)
    $this->assertDatabaseHas('services', [
        'id' => $data['service']->id,
        'rating_count' => 0,
    ]);
})->group('reviews');

it('excludes soft-deleted reviews from aggregation', function (): void {
    $data = makeCompletedBookingItem();

    $review = ServiceReview::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $data['service']->id,
        'booking_item_id' => $data['bookingItem']->id,
        'user_id' => $data['customer']->id,
        'rating' => 5,
        'locale' => 'en',
        'moderation_status' => 'approved',
    ]);

    // Soft delete the review
    $review->delete();

    app(RecomputeRatingOnApproval::class)->handle(new ReviewSelfDeleted(
        reviewId: $review->id,
        reviewType: 'service',
        subjectId: $data['service']->id,
        previousModerationStatus: 'approved',
    ));

    $this->assertDatabaseHas('services', [
        'id' => $data['service']->id,
        'rating_count' => 0,
    ]);
})->group('reviews');
