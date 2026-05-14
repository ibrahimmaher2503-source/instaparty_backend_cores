<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\ListBookingPaymentsAction;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Happy path
// ─────────────────────────────────────────────────────────────────────────────

it('returns payments for the booking associated with the vendor', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $bv = $data['bookingVendor'];
    $vendor = $data['vendor'];
    $booking = $data['booking'];

    Payment::factory()->captured()->count(3)->create([
        'booking_id' => $booking->id,
        'user_id' => $data['customer']->id,
    ]);

    $bv->update(['sub_status' => VendorSubStatus::Accepted]);

    $payments = app(ListBookingPaymentsAction::class)->execute($bv, $vendor);

    expect($payments)->toHaveCount(3);
    expect($payments->first()->booking_id)->toBe($booking->id);
})->group('booking', 'payments', 'vendor');

it('returns payments ordered by created_at descending', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $bv = $data['bookingVendor'];
    $booking = $data['booking'];

    $first = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $data['customer']->id,
        'created_at' => now()->subMinutes(10),
    ]);
    $second = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $data['customer']->id,
        'created_at' => now(),
    ]);

    $result = app(ListBookingPaymentsAction::class)->execute($bv, $data['vendor']);

    expect($result->first()->id)->toBe($second->id);
    expect($result->last()->id)->toBe($first->id);
})->group('booking', 'payments', 'vendor');

it('returns an empty collection when no payments exist', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $bv = $data['bookingVendor'];

    $result = app(ListBookingPaymentsAction::class)->execute($bv, $data['vendor']);

    expect($result)->toBeEmpty();
})->group('booking', 'payments', 'vendor');

it('includes refunded payments in the list', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $bv = $data['bookingVendor'];
    $booking = $data['booking'];

    Payment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $data['customer']->id,
        'status' => PaymentStatus::Refunded,
        'amount_minor' => 50000,
    ]);

    $result = app(ListBookingPaymentsAction::class)->execute($bv, $data['vendor']);

    expect($result)->toHaveCount(1);
    expect($result->first()->status)->toBe(PaymentStatus::Refunded);
})->group('booking', 'payments', 'vendor');

// ─────────────────────────────────────────────────────────────────────────────
// Ownership guard — cross-vendor leakage prevention
// ─────────────────────────────────────────────────────────────────────────────

it('throws ValidationException when a different vendor requests the payment list', function (): void {
    $data = makeSubmittedBookingWithVendor();
    $bv = $data['bookingVendor'];

    $otherVendor = VendorProfile::factory()->approved()->create();

    expect(fn () => app(ListBookingPaymentsAction::class)->execute($bv, $otherVendor))
        ->toThrow(ValidationException::class);
})->group('booking', 'payments', 'auth');
