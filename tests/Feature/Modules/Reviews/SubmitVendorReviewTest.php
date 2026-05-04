<?php

declare(strict_types=1);

use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('creates a vendor review when all booking vendor items are completed', function (): void {
    $data = makeCompletedBookingItem();

    // All items are completed — vendor review eligible
    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-vendors/{$data['bookingVendor']->public_id}/review", [
            'rating' => 4,
            'body' => 'Great vendor service!',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.rating', 4)
        ->assertJsonPath('data.moderation_status', ModerationStatus::Pending->value);

    $this->assertDatabaseHas('vendor_reviews', [
        'booking_vendor_id' => $data['bookingVendor']->id,
        'moderation_status' => 'pending',
        'rating' => 4,
    ]);
})->group('reviews');

it('returns 422 when not all items are completed', function (): void {
    $data = makeCompletedBookingItem();
    $data['bookingItem']->update(['item_status' => 'pending']);

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-vendors/{$data['bookingVendor']->public_id}/review", [
            'rating' => 4,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'booking_vendor_items_not_all_completed');
})->group('reviews');

it('returns 409 when vendor review already exists', function (): void {
    $data = makeCompletedBookingItem();

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-vendors/{$data['bookingVendor']->public_id}/review", ['rating' => 4])
        ->assertStatus(201);

    $this->actingAs($data['customer'], 'sanctum')
        ->postJson("/api/v1/customer/booking-vendors/{$data['bookingVendor']->public_id}/review", ['rating' => 3])
        ->assertStatus(409)
        ->assertJsonPath('errors.0.code', 'review_already_exists');
})->group('reviews');

it('returns 401 when unauthenticated for vendor review', function (): void {
    $data = makeCompletedBookingItem();

    $this->postJson("/api/v1/customer/booking-vendors/{$data['bookingVendor']->public_id}/review", ['rating' => 4])
        ->assertStatus(401);
})->group('reviews');
