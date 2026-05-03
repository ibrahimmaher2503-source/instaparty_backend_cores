<?php

declare(strict_types=1);

use App\Modules\Payments\Application\Actions\ExpirePendingPaymentsAction;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('marks pending payments past the booking hold as failed=expired_payment_hold', function (): void {
    $data = makeConfirmedBookingWithItem();
    $data['booking']->update(['payment_hold_expires_at' => now()->subMinutes(5)]);

    $payment = Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => 'PMB-EXP-'.Str::ulid(),
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Pending,
    ]);

    app(ExpirePendingPaymentsAction::class)->execute();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Failed);
    expect($payment->failure_code)->toBe('expired_payment_hold');
})->group('payments');

it('does not touch pending payments still within the hold window', function (): void {
    $data = makeConfirmedBookingWithItem();
    $data['booking']->update(['payment_hold_expires_at' => now()->addHours(12)]);

    $payment = Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => 'PMB-FRESH-'.Str::ulid(),
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Pending,
    ]);

    app(ExpirePendingPaymentsAction::class)->execute();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
})->group('payments');

it('is idempotent — re-running on already-expired payments does nothing', function (): void {
    $data = makeConfirmedBookingWithItem();
    $data['booking']->update(['payment_hold_expires_at' => now()->subHours(1)]);

    Payment::create([
        'public_id' => (string) Str::ulid(),
        'booking_id' => $data['booking']->id,
        'user_id' => $data['customer']->id,
        'gateway' => 'paymob',
        'gateway_ref' => 'PMB-IDEM-'.Str::ulid(),
        'amount_minor' => 50000,
        'amount_currency' => 'EGP',
        'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Pending,
    ]);

    app(ExpirePendingPaymentsAction::class)->execute();
    app(ExpirePendingPaymentsAction::class)->execute();

    expect(Payment::where('status', PaymentStatus::Failed)->count())->toBe(1);
})->group('payments');
