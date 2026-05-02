<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function refundSaleRequest(User $admin, string $bookingPublicId): TestResponse
{
    return test()->withHeaders([
        'Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson("/api/v1/admin/bookings/{$bookingPublicId}/refunds", [
        'reason_code' => 'customer_request',
        'reason_notes' => ['en' => 'Customer requested', 'ar' => 'طلب العميل'],
    ]);
}

it('sale: refund allowed when item_status is pending', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Sale, 'pending');
    makeCapturedPayment($data['booking'], $data['customer']);

    refundSaleRequest(makeAdminUser(), $data['booking']->public_id)->assertStatus(201);
    expect(Refund::where('booking_id', $data['booking']->id)->count())->toBe(1);
})->group('payments', 'sale');

it('sale: refund allowed when item_status is confirmed', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Sale, 'confirmed');
    makeCapturedPayment($data['booking'], $data['customer']);

    refundSaleRequest(makeAdminUser(), $data['booking']->public_id)->assertStatus(201);
})->group('payments', 'sale');

it('sale: 422 with sale_in_preparation when item is in_preparation', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Sale, 'in_preparation');
    makeCapturedPayment($data['booking'], $data['customer']);

    $response = refundSaleRequest(makeAdminUser(), $data['booking']->public_id);

    $response->assertStatus(422);
    expect($response->json('errors.code'))->toBe('sale_in_preparation');
})->group('payments', 'sale');

it('sale: 422 when item is out_for_delivery', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Sale, 'out_for_delivery');
    makeCapturedPayment($data['booking'], $data['customer']);

    refundSaleRequest(makeAdminUser(), $data['booking']->public_id)->assertStatus(422);
})->group('payments', 'sale');

it('sale: 422 when item is delivered', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Sale, 'delivered');
    makeCapturedPayment($data['booking'], $data['customer']);

    refundSaleRequest(makeAdminUser(), $data['booking']->public_id)->assertStatus(422);
})->group('payments', 'sale');

it('returns 401 when unauthenticated', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Sale, 'confirmed');

    $response = $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/admin/bookings/{$data['booking']->public_id}/refunds", [
            'reason_code' => 'customer_request',
            'reason_notes' => ['en' => 'x', 'ar' => 'x'],
        ]);

    $response->assertStatus(401);
})->group('payments', 'sale');
