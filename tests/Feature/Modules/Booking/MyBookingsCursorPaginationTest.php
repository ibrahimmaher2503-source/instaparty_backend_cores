<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('booking', 'cursor');

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('returns first page with next_cursor when more rows exist', function (): void {
    $user = User::factory()->asCustomer()->create();
    Booking::factory()->count(25)->create(['customer_id' => $user->id]);

    $response = $this->withToken($user->createToken('t')->plainTextToken)
        ->getJson('/api/v1/customer/bookings')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.next_cursor'))->not->toBeNull();
});

it('returns last page with null next_cursor', function (): void {
    $user = User::factory()->asCustomer()->create();
    Booking::factory()->count(15)->create(['customer_id' => $user->id]);

    $response = $this->withToken($user->createToken('t')->plainTextToken)
        ->getJson('/api/v1/customer/bookings')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(15);
    expect($response->json('meta.next_cursor'))->toBeNull();
});

it('paginates without skip or duplication across same-second created_at', function (): void {
    $user = User::factory()->asCustomer()->create();
    $now = now();
    Booking::factory()->count(25)->create(['customer_id' => $user->id, 'created_at' => $now]);

    $token = $user->createToken('t')->plainTextToken;
    $page1 = $this->withToken($token)->getJson('/api/v1/customer/bookings')->json();
    $page2 = $this->withToken($token)->getJson('/api/v1/customer/bookings?cursor='.$page1['meta']['next_cursor'])->json();

    $ids = collect([...$page1['data'], ...$page2['data']])->pluck('public_id');
    expect($ids)->toHaveCount(25);
    expect($ids->unique())->toHaveCount(25);
});

it('rejects invalid cursor with 422', function (): void {
    $user = User::factory()->asCustomer()->create();

    $this->withToken($user->createToken('t')->plainTextToken)
        ->getJson('/api/v1/customer/bookings?cursor=not-valid-base64!!!')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cursor']);
});

it('filters by status', function (): void {
    $user = User::factory()->asCustomer()->create();
    Booking::factory()->count(3)->create(['customer_id' => $user->id, 'lifecycle_status' => 'completed']);
    Booking::factory()->count(5)->create(['customer_id' => $user->id, 'lifecycle_status' => 'draft']);

    $response = $this->withToken($user->createToken('t')->plainTextToken)
        ->getJson('/api/v1/customer/bookings?status=completed')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('maps the upcoming bucket to post-submission pre-event states', function (): void {
    $user = User::factory()->asCustomer()->create();
    Booking::factory()->create(['customer_id' => $user->id, 'lifecycle_status' => 'confirmed']);
    Booking::factory()->create(['customer_id' => $user->id, 'lifecycle_status' => 'vendor_review']);
    Booking::factory()->create(['customer_id' => $user->id, 'lifecycle_status' => 'draft']);      // excluded
    Booking::factory()->create(['customer_id' => $user->id, 'lifecycle_status' => 'completed']);   // excluded

    $response = $this->withToken($user->createToken('t')->plainTextToken)
        ->getJson('/api/v1/customer/bookings?status=upcoming')
        ->assertOk();

    // confirmed + vendor_review only (was always-empty before the fix).
    expect($response->json('data'))->toHaveCount(2);
});

it('rejects unknown status with 422', function (): void {
    $user = User::factory()->asCustomer()->create();

    $this->withToken($user->createToken('t')->plainTextToken)
        ->getJson('/api/v1/customer/bookings?status=unknown_status')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('returns 401 when unauthenticated', function (): void {
    getJson('/api/v1/customer/bookings')->assertUnauthorized();
});
