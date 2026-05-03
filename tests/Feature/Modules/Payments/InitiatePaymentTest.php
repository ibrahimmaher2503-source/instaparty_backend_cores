<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('customer can initiate payment on confirmed booking (happy path)', function (): void {
    $data = makeConfirmedBookingWithItem();
    $token = $data['customer']->createToken('test')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", [
        'method' => 'card',
    ]);

    $response->assertStatus(201);
    expect(Payment::where('booking_id', $data['booking']->id)->count())->toBe(1);
})->group('payments');

it('returns 401 when unauthenticated', function (): void {
    $data = makeConfirmedBookingWithItem();

    $response = $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", ['method' => 'card']);

    $response->assertStatus(401);
})->group('payments');

it('returns 403 when customer A tries to pay customer B booking', function (): void {
    $data = makeConfirmedBookingWithItem();
    $otherCustomer = User::factory()->asCustomer()->create();
    $token = $otherCustomer->createToken('test')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", ['method' => 'card']);

    expect($response->status())->toBeIn([403, 404]);
})->group('payments');

it('returns 422 when method is missing', function (): void {
    $data = makeConfirmedBookingWithItem();
    $token = $data['customer']->createToken('test')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", []);

    $response->assertStatus(422);
})->group('payments');

it('returns 422 when method is wallet (Phase 1 only allows card)', function (): void {
    $data = makeConfirmedBookingWithItem();
    $token = $data['customer']->createToken('test')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", ['method' => 'wallet']);

    $response->assertStatus(422);
})->group('payments');

it('idempotent replay: same key returns same response without creating duplicate', function (): void {
    $data = makeConfirmedBookingWithItem();
    $token = $data['customer']->createToken('test')->plainTextToken;
    $key = (string) Str::uuid();

    $first = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => $key])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", ['method' => 'card']);

    $second = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => $key])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", ['method' => 'card']);

    expect($first->status())->toBe(201);
    expect($second->status())->toBe(201);
    expect(Payment::where('booking_id', $data['booking']->id)->count())->toBe(1);
})->group('payments');

it('idempotent conflict: same key with different body returns 409', function (): void {
    $data = makeConfirmedBookingWithItem();
    $token = $data['customer']->createToken('test')->plainTextToken;
    $key = (string) Str::uuid();

    $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => $key])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", ['method' => 'card']);

    $second = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => $key])
        ->postJson("/api/v1/customer/bookings/{$data['booking']->public_id}/payments", ['method' => 'card', 'extra' => 'tampered']);

    $second->assertStatus(409);
})->group('payments');
