<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function refundRentalRequest(User $admin, string $bookingPublicId, array $body = []): TestResponse
{
    $token = $admin->createToken('test')->plainTextToken;

    return test()->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
        'Accept-Language' => $body['_lang'] ?? 'en',
    ])->postJson("/api/v1/admin/bookings/{$bookingPublicId}/refunds", array_filter([
        'reason_code' => $body['reason_code'] ?? 'customer_request',
        'reason_notes' => $body['reason_notes'] ?? ['en' => 'Customer requested', 'ar' => 'طلب العميل'],
        'amount_minor' => $body['amount_minor'] ?? null,
    ], fn ($v) => $v !== null));
}

it('rental: refund allowed when event is more than 24h away (happy path)', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'confirmed', now()->addHours(48));
    makeCapturedPayment($data['booking'], $data['customer']);
    $admin = makeAdminUser();

    $response = refundRentalRequest($admin, $data['booking']->public_id);

    $response->assertStatus(201);
    expect(Refund::where('booking_id', $data['booking']->id)->count())->toBe(1);
})->group('payments', 'rental');

it('rental: refund allowed just inside the 24h boundary', function (): void {
    // Use 24h + 1 minute to avoid clock-skew flakiness on the exact boundary.
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'confirmed', now()->addHours(24)->addMinute());
    makeCapturedPayment($data['booking'], $data['customer']);
    $admin = makeAdminUser();

    refundRentalRequest($admin, $data['booking']->public_id)->assertStatus(201);
})->group('payments', 'rental');

it('rental: 422 with rental_window_closed when event is less than 24h away', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'confirmed', now()->addHours(23));
    makeCapturedPayment($data['booking'], $data['customer']);
    $admin = makeAdminUser();

    $response = refundRentalRequest($admin, $data['booking']->public_id);

    $response->assertStatus(422);
    expect($response->json('errors.code'))->toBe('rental_window_closed');
})->group('payments', 'rental');

it('rental: 422 with rental_in_setup when item is in setup state', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'setup', now()->addHours(48));
    makeCapturedPayment($data['booking'], $data['customer']);
    $admin = makeAdminUser();

    $response = refundRentalRequest($admin, $data['booking']->public_id);

    $response->assertStatus(422);
    expect($response->json('errors.code'))->toBe('rental_in_setup');
})->group('payments', 'rental');

it('returns 401 when unauthenticated', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'confirmed', now()->addHours(48));

    $response = $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/admin/bookings/{$data['booking']->public_id}/refunds", [
            'reason_code' => 'customer_request',
            'reason_notes' => ['en' => 'x', 'ar' => 'x'],
        ]);

    $response->assertStatus(401);
})->group('payments', 'rental');

it('returns 422 when reason_notes.ar is missing', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'confirmed', now()->addHours(48));
    makeCapturedPayment($data['booking'], $data['customer']);
    $admin = makeAdminUser();

    $response = refundRentalRequest($admin, $data['booking']->public_id, [
        'reason_notes' => ['en' => 'Only english'],
    ]);

    $response->assertStatus(422);
})->group('payments', 'rental');

it('returns 422 with partial_refund_unsupported when amount_minor < payment amount', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'confirmed', now()->addHours(48));
    makeCapturedPayment($data['booking'], $data['customer'], 50000);
    $admin = makeAdminUser();

    $response = refundRentalRequest($admin, $data['booking']->public_id, ['amount_minor' => 25000]);

    $response->assertStatus(422);
    expect($response->json('errors.code'))->toBe('partial_refund_unsupported');
})->group('payments', 'rental');

it('rental: AR locale returns Arabic policy violation message', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Rental, 'confirmed', now()->addHours(23));
    makeCapturedPayment($data['booking'], $data['customer']);
    $admin = makeAdminUser();

    $response = refundRentalRequest($admin, $data['booking']->public_id, ['_lang' => 'ar']);

    $response->assertStatus(422);
    expect($response->json('errors.message'))->toContain('انتهت');
})->group('payments', 'rental');
