<?php

declare(strict_types=1);

use App\Modules\Reviews\Domain\Models\ServiceReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

function makeServiceReviews(int $count, string $status = 'approved'): array
{
    $data = makeCompletedBookingItem();

    $reviews = [];
    for ($i = 0; $i < $count; $i++) {
        // Create a new booking item for each review (unique booking_item_id)
        $newData = makeCompletedBookingItem();
        $reviews[] = ServiceReview::create([
            'public_id' => (string) Str::ulid(),
            'service_id' => $data['service']->id,
            'booking_item_id' => $newData['bookingItem']->id,
            'user_id' => $newData['customer']->id,
            'rating' => rand(1, 5),
            'body' => null,
            'locale' => 'en',
            'moderation_status' => $status,
        ]);
    }

    return ['service' => $data['service'], 'reviews' => $reviews, 'customer' => $data['customer']];
}

it('returns only approved non-deleted reviews for a service', function (): void {
    $serviceData = makeServiceReviews(3, 'approved');
    $service = $serviceData['service'];

    // Add pending and rejected reviews
    makeServiceReviews(2, 'pending');
    makeServiceReviews(1, 'rejected');

    $this->getJson("/api/v1/public/services/{$service->public_id}/reviews")
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
})->group('reviews');

it('returns reviewer_first_name as first word of name', function (): void {
    $data = makeCompletedBookingItem();
    $data['customer']->update(['name' => 'Ahmed Maher']);

    ServiceReview::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $data['service']->id,
        'booking_item_id' => $data['bookingItem']->id,
        'user_id' => $data['customer']->id,
        'rating' => 5,
        'locale' => 'en',
        'moderation_status' => 'approved',
    ]);

    $this->getJson("/api/v1/public/services/{$data['service']->public_id}/reviews")
        ->assertStatus(200)
        ->assertJsonPath('data.0.reviewer_first_name', 'Ahmed');
})->group('reviews');

it('returns verified customer fallback when user name is empty', function (): void {
    $data = makeCompletedBookingItem();
    $data['customer']->update(['name' => '']);

    ServiceReview::create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $data['service']->id,
        'booking_item_id' => $data['bookingItem']->id,
        'user_id' => $data['customer']->id,
        'rating' => 5,
        'locale' => 'en',
        'moderation_status' => 'approved',
    ]);

    $this->getJson("/api/v1/public/services/{$data['service']->public_id}/reviews")
        ->assertStatus(200)
        ->assertJsonPath('data.0.reviewer_first_name', 'Verified Customer');
})->group('reviews');

it('returns 404 for non-existent service', function (): void {
    $this->getJson('/api/v1/public/services/NONEXISTENT123456789012345/reviews')
        ->assertStatus(404);
})->group('reviews');

it('soft-deleted reviews do not appear in public listing', function (): void {
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

    $review->delete();

    $this->getJson("/api/v1/public/services/{$data['service']->public_id}/reviews")
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
})->group('reviews');
