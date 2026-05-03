<?php

declare(strict_types=1);

use App\Modules\Reviews\Application\Actions\ModerateReviewAction;
use App\Modules\Reviews\Application\DTOs\ModerateReviewData;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeServiceReview(string $status = 'pending'): array
{
    $data = makeCompletedBookingItem();
    $review = ServiceReview::create([
        'public_id'         => (string) Str::ulid(),
        'service_id'        => $data['service']->id,
        'booking_item_id'   => $data['bookingItem']->id,
        'user_id'           => $data['customer']->id,
        'rating'            => 4,
        'body'              => 'Test review',
        'locale'            => 'en',
        'moderation_status' => $status,
    ]);

    return array_merge($data, ['review' => $review]);
}

it('approves a pending review (pending → approved)', function (): void {
    $data = makeServiceReview('pending');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Approved,
        moderatorId: $admin->id,
    ));

    $this->assertDatabaseHas('service_reviews', [
        'id'                => $data['review']->id,
        'moderation_status' => 'approved',
        'moderated_by'      => $admin->id,
    ]);

    $this->assertDatabaseHas('review_moderation_log', [
        'review_type' => 'service',
        'review_id'   => $data['review']->id,
        'from_status' => 'pending',
        'to_status'   => 'approved',
        'moderator_id' => $admin->id,
    ]);
})->group('reviews');

it('rejects a pending review (pending → rejected)', function (): void {
    $data = makeServiceReview('pending');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Rejected,
        moderatorId: $admin->id,
        reason: ['en' => 'Inappropriate', 'ar' => 'غير لائق'],
    ));

    $this->assertDatabaseHas('service_reviews', [
        'id'                => $data['review']->id,
        'moderation_status' => 'rejected',
    ]);
})->group('reviews');

it('hides an approved review (approved → hidden)', function (): void {
    $data = makeServiceReview('approved');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Hidden,
        moderatorId: $admin->id,
    ));

    $this->assertDatabaseHas('service_reviews', [
        'id'                => $data['review']->id,
        'moderation_status' => 'hidden',
    ]);

    $this->assertDatabaseHas('review_moderation_log', [
        'from_status' => 'approved',
        'to_status'   => 'hidden',
    ]);
})->group('reviews');

it('restores a hidden review (hidden → approved)', function (): void {
    $data = makeServiceReview('hidden');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Approved,
        moderatorId: $admin->id,
    ));

    $this->assertDatabaseHas('service_reviews', [
        'id'                => $data['review']->id,
        'moderation_status' => 'approved',
    ]);
})->group('reviews');

it('rejects an approved review (approved → rejected)', function (): void {
    $data = makeServiceReview('approved');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Rejected,
        moderatorId: $admin->id,
    ));

    $this->assertDatabaseHas('service_reviews', [
        'id'                => $data['review']->id,
        'moderation_status' => 'rejected',
    ]);
})->group('reviews');

it('throws when transitioning from rejected (terminal state)', function (): void {
    $data = makeServiceReview('rejected');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    expect(fn () => app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Approved,
        moderatorId: $admin->id,
    )))->toThrow(\InvalidArgumentException::class);
})->group('reviews');

it('throws when transitioning pending → hidden (forbidden)', function (): void {
    $data = makeServiceReview('pending');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    expect(fn () => app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Hidden,
        moderatorId: $admin->id,
    )))->toThrow(\InvalidArgumentException::class);
})->group('reviews');

it('appends to review_moderation_log on every transition', function (): void {
    $data = makeServiceReview('pending');
    $admin = \App\Modules\Identity\Domain\Models\User::factory()->asAdmin()->create();

    app(ModerateReviewAction::class)->execute($data['review'], new ModerateReviewData(
        toStatus: ModerationStatus::Approved,
        moderatorId: $admin->id,
    ));

    $this->assertDatabaseCount('review_moderation_log', 1);
})->group('reviews');
