<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function refundDigitalRequest(User $admin, string $bookingPublicId): TestResponse
{
    return test()->withHeaders([
        'Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson("/api/v1/admin/bookings/{$bookingPublicId}/refunds", [
        'reason_code' => 'customer_request',
        'reason_notes' => ['en' => 'Customer requested', 'ar' => 'طلب العميل'],
    ]);
}

function setDigitalRefundFlag(int $serviceId, bool $flag): void
{
    DB::table('service_digital_details')->updateOrInsert(
        ['service_id' => $serviceId],
        ['is_refundable_after_delivery' => $flag, 'delivery_method' => 'email'],
    );
}

it('digital: refund allowed pre-delivery regardless of refundable flag (flag=false)', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Digital, 'pending');
    setDigitalRefundFlag($data['service']->id, false);
    makeCapturedPayment($data['booking'], $data['customer']);

    refundDigitalRequest(makeAdminUser(), $data['booking']->public_id)->assertStatus(201);
})->group('payments', 'digital');

it('digital: refund allowed post-delivery when service flag is true', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Digital, 'delivered');
    setDigitalRefundFlag($data['service']->id, true);
    makeCapturedPayment($data['booking'], $data['customer']);

    refundDigitalRequest(makeAdminUser(), $data['booking']->public_id)->assertStatus(201);
})->group('payments', 'digital');

it('digital: 422 digital_post_delivery when delivered and service flag is false', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Digital, 'delivered');
    setDigitalRefundFlag($data['service']->id, false);
    makeCapturedPayment($data['booking'], $data['customer']);

    $response = refundDigitalRequest(makeAdminUser(), $data['booking']->public_id);

    $response->assertStatus(422);
    expect($response->json('errors.code'))->toBe('digital_post_delivery');
})->group('payments', 'digital');

it('returns 401 when unauthenticated', function (): void {
    $data = makeConfirmedBookingWithItem(ProductType::Digital, 'pending');

    $response = $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/admin/bookings/{$data['booking']->public_id}/refunds", [
            'reason_code' => 'customer_request',
            'reason_notes' => ['en' => 'x', 'ar' => 'x'],
        ]);

    $response->assertStatus(401);
})->group('payments', 'digital');
