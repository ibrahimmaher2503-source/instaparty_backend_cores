<?php

declare(strict_types=1);

use App\Modules\Reviews\Domain\Models\VendorReview;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('returns only approved non-deleted vendor reviews', function (): void {
    $data = makeCompletedBookingItem();
    $vendor = $data['vendor'];

    VendorReview::create([
        'public_id'         => (string) Str::ulid(),
        'vendor_profile_id' => $vendor->id,
        'booking_vendor_id' => $data['bookingVendor']->id,
        'user_id'           => $data['customer']->id,
        'rating'            => 5,
        'locale'            => 'en',
        'moderation_status' => 'approved',
    ]);

    $data2 = makeCompletedBookingItem();
    VendorReview::create([
        'public_id'         => (string) Str::ulid(),
        'vendor_profile_id' => $vendor->id,
        'booking_vendor_id' => $data2['bookingVendor']->id,
        'user_id'           => $data2['customer']->id,
        'rating'            => 3,
        'locale'            => 'en',
        'moderation_status' => 'pending',
    ]);

    $this->getJson("/api/v1/public/vendors/{$vendor->public_id}/reviews")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
})->group('reviews');

it('returns 404 for non-existent vendor', function (): void {
    $this->getJson('/api/v1/public/vendors/NONEXISTENT123456789012345/reviews')
        ->assertStatus(404);
})->group('reviews');
