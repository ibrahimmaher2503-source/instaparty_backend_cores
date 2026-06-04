<?php

declare(strict_types=1);

use App\Modules\Payments\Application\Actions\ExpirePendingPaymentsAction;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\FailedState;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use Database\Seeders\IdentityRolesSeeder;

/**
 * P1 — Customer API audit 2026-06-04: payment-hold expiry behavior on the
 * draft-booking ("cart") flow. The hold deadline is exposed to the frontend
 * countdown via BookingResource.hold_expires_at, and the scheduled
 * ExpirePendingPaymentsAction fails pending payments on stale-hold bookings.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('booking detail exposes hold_expires_at for the frontend countdown', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['booking']->update(['payment_hold_expires_at' => now()->addHours(24)]);

    $response = $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}")
        ->assertStatus(200);

    expect($response->json('data.hold_expires_at'))->not->toBeNull();

    $expiry = new DateTimeImmutable($response->json('data.hold_expires_at'));
    expect($expiry->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
})->group('booking', 'hold-expiry');

it('expires pending payments on bookings whose payment hold lapsed, bilingually', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['booking']->update(['payment_hold_expires_at' => now()->subMinutes(5)]);

    $payment = Payment::factory()->create([
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'status' => 'pending',
    ]);

    app(ExpirePendingPaymentsAction::class)->execute();

    $payment->refresh();

    expect($payment->status)->toBeInstanceOf(FailedState::class)
        ->and($payment->failure_code)->toBe('expired_payment_hold')
        ->and($payment->failure_message)->toHaveKeys(['en', 'ar']);
})->group('booking', 'payments', 'hold-expiry');

it('leaves pending payments untouched while the hold is still fresh', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $data['booking']->update(['payment_hold_expires_at' => now()->addHours(24)]);

    $payment = Payment::factory()->create([
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'status' => 'pending',
    ]);

    app(ExpirePendingPaymentsAction::class)->execute();

    expect($payment->refresh()->status)->toBeInstanceOf(PendingState::class);
})->group('booking', 'payments', 'hold-expiry');

it('rejects initiate-payment on a booking whose hold expired')
    ->todo()
    // Audit 2026-06-04 gap: InitiatePaymentAction does not check
    // payment_hold_expires_at — an expired-hold booking can still mint a
    // Paymob intent. Enforcement lands in Phase 3 with the cancel flow.
    ->group('booking', 'payments', 'hold-expiry');
