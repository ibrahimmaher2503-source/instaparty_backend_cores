<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function bookingCustomerUser(): User
{
    return User::factory()->asCustomer()->create(['timezone' => 'Africa/Cairo']);
}

function bookingOccasion(): Occasion
{
    return Occasion::factory()->create();
}

function bookingCity(): City
{
    return City::factory()->create();
}

function validBookingPayload(string $occasionPublicId, string $cityPublicId): array
{
    return [
        'occasion_id' => $occasionPublicId,
        'event_starts_at' => now()->addDays(30)->toIso8601String(),
        'event_ends_at' => now()->addDays(30)->addHours(5)->toIso8601String(),
        'guest_count' => 50,
        'address' => [
            'city_id' => $cityPublicId,
            'address_line' => '12 Nile St',
            'recipient_name' => 'Aya Maher',
            'recipient_phone_e164' => '+201012345678',
        ],
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('creates a draft booking and returns 201', function (): void {
    $user = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/customer/bookings', validBookingPayload($occasion->public_id, $city->public_id));

    $response->assertStatus(201);
    $response->assertJsonPath('data.lifecycle_status', 'draft');
    expect($response->json('data.public_id'))->not->toBeNull();
})->group('booking', 'draft');

it('returns 401 for unauthenticated request', function (): void {
    $this->postJson('/api/v1/customer/bookings', [])->assertStatus(401);
})->group('booking', 'draft');

it('returns 422 when event_starts_at is missing', function (): void {
    $user = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $payload = validBookingPayload($occasion->public_id, $city->public_id);
    unset($payload['event_starts_at']);

    $this->actingAs($user)->postJson('/api/v1/customer/bookings', $payload)->assertStatus(422);
})->group('booking', 'draft');

it('returns 422 when address.city_id is missing', function (): void {
    $user = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $payload = validBookingPayload($occasion->public_id, $city->public_id);
    unset($payload['address']['city_id']);

    $this->actingAs($user)->postJson('/api/v1/customer/bookings', $payload)->assertStatus(422);
})->group('booking', 'draft');

it('returns 422 when occasion_id is missing', function (): void {
    $user = bookingCustomerUser();
    $city = bookingCity();

    $payload = validBookingPayload('nonexistent-occasion', $city->public_id);

    $this->actingAs($user)->postJson('/api/v1/customer/bookings', $payload)->assertStatus(422);
})->group('booking', 'draft');

it('creates a booking_snapshots row with version 1', function (): void {
    $user = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/customer/bookings', validBookingPayload($occasion->public_id, $city->public_id));

    $response->assertStatus(201);

    $publicId = $response->json('data.public_id');
    $booking = Booking::where('public_id', $publicId)->first();

    expect(
        BookingSnapshot::where('booking_id', $booking->id)->where('version', 1)->exists()
    )->toBeTrue();
})->group('booking', 'draft');

it('creates a booking_state_transitions row with to_state=draft', function (): void {
    $user = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/customer/bookings', validBookingPayload($occasion->public_id, $city->public_id));

    $response->assertStatus(201);

    $publicId = $response->json('data.public_id');
    $booking = Booking::where('public_id', $publicId)->first();

    expect(
        BookingStateTransition::where('transitionable_id', $booking->id)
            ->where('to_state', 'draft')
            ->exists()
    )->toBeTrue();
})->group('booking', 'draft');

it('stores a reference_no with IP- prefix', function (): void {
    $user = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/customer/bookings', validBookingPayload($occasion->public_id, $city->public_id));

    $response->assertStatus(201);
    expect($response->json('data.reference_no'))->toStartWith('IP-');
})->group('booking', 'draft');

it('show returns the booking for the owning customer', function (): void {
    $user = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $create = $this->actingAs($user)
        ->postJson('/api/v1/customer/bookings', validBookingPayload($occasion->public_id, $city->public_id));

    $create->assertStatus(201);
    $publicId = $create->json('data.public_id');

    $show = $this->actingAs($user)->getJson("/api/v1/customer/bookings/{$publicId}");
    $show->assertStatus(200);
    $show->assertJsonPath('data.public_id', $publicId);
})->group('booking', 'draft');

it('show returns 404 for another customer', function (): void {
    $owner = bookingCustomerUser();
    $other = bookingCustomerUser();
    $occasion = bookingOccasion();
    $city = bookingCity();

    $create = $this->actingAs($owner)
        ->postJson('/api/v1/customer/bookings', validBookingPayload($occasion->public_id, $city->public_id));

    $create->assertStatus(201);
    $publicId = $create->json('data.public_id');

    $this->actingAs($other)
        ->getJson("/api/v1/customer/bookings/{$publicId}")
        ->assertStatus(404);
})->group('booking', 'draft');
