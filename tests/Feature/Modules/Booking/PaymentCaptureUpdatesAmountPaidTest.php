<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Listeners\UpdateBookingPaymentStatusListener;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Review finding #1: the cancellation refund pipeline gates on
 * bookings.amount_paid_minor, which was never written on capture. This pins
 * that PaymentCaptured now populates it (and RefundCompleted draws it down).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

it('capture increments amount_paid_minor and refund draws it down', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $booking = $data['booking'];

    expect((int) $booking->refresh()->amount_paid_minor)->toBe(0);

    $listener = app(UpdateBookingPaymentStatusListener::class);

    $listener->handle(new PaymentCaptured(
        paymentId: 1,
        bookingId: (int) $booking->id,
        amountMinor: 50000,
        amountCurrency: 'EGP',
        capturedAt: now(),
    ));

    $booking->refresh();
    expect((int) $booking->amount_paid_minor)->toBe(50000)
        ->and($booking->payment_status->getValue())->toBe('paid');

    $listener->handleRefund(new RefundCompleted(
        refundId: 1,
        paymentId: 1,
        bookingId: (int) $booking->id,
        amountMinor: 50000,
        amountCurrency: 'EGP',
        reasonCode: 'customer_request',
    ));

    expect((int) $booking->refresh()->amount_paid_minor)->toBe(0);
})->group('booking', 'payments');

it('cancellation preview reflects the captured amount once paid', function (): void {
    $data = makeSubmittedBookingWithVendor();

    DB::table('bookings')->where('id', $data['booking']->id)->update(['amount_paid_minor' => 50000]);

    $this->actingAs($data['customer'])
        ->getJson("/api/v1/customer/bookings/{$data['booking']->public_id}/cancellation-preview")
        ->assertStatus(200)
        ->assertJsonPath('data.amount_paid_minor', 50000);
})->group('booking', 'payments', 'cancellation');
