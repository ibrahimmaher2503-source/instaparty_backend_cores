<?php

declare(strict_types=1);

use App\Modules\Payments\Application\Actions\ProcessRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Domain\Exceptions\OverRefundAttemptedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('three partial refunds summing to the captured total all succeed and produce three ledger groups', function (): void {
    $data    = makeConfirmedBookingWithItem();
    $booking = $data['booking'];

    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $booking->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-PARTIAL-' . Str::ulid(),
        'amount_minor'    => 100_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Captured,
    ]);

    $action = app(ProcessRefundAction::class);

    // Create and process 3 partial refund records: 30 000 + 40 000 + 30 000 = 100 000
    foreach ([30_000, 40_000, 30_000] as $amount) {
        $refund = Refund::create([
            'public_id'       => (string) Str::ulid(),
            'payment_id'      => $payment->id,
            'booking_id'      => $booking->id,
            'amount_minor'    => $amount,
            'amount_currency' => 'EGP',
            'reason_code'     => RefundReasonCode::CustomerRequest,
            'reason_notes'    => ['en' => 'partial test', 'ar' => 'اختبار جزئي'],
            'status'          => RefundStatus::Pending,
            'initiated_by'    => $data['customer']->id,
        ]);

        $action->execute($refund->id);
    }

    expect(DB::table('ledger_transaction_groups')->where('kind', 'refund')->count())->toBe(3);

    // All three refunds should be completed
    $completedCount = Refund::query()
        ->where('payment_id', $payment->id)
        ->where('status', RefundStatus::Completed)
        ->count();
    expect($completedCount)->toBe(3);

    // Each refund group has exactly 2 entries (debit + credit)
    DB::table('ledger_transaction_groups')->where('kind', 'refund')->each(function ($group): void {
        expect(DB::table('wallet_ledger')->where('transaction_group_id', $group->id)->count())->toBe(2);
    });
})->group('us4');

it('fourth refund attempt after fully exhausting captured total throws OverRefundAttemptedException', function (): void {
    $data    = makeConfirmedBookingWithItem();
    $booking = $data['booking'];

    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $booking->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-EXHAUST-' . Str::ulid(),
        'amount_minor'    => 60_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Captured,
    ]);

    $processAction = app(ProcessRefundAction::class);

    // Exhaust the captured total with two refunds: 40 000 + 20 000 = 60 000
    foreach ([40_000, 20_000] as $amount) {
        $refund = Refund::create([
            'public_id'       => (string) Str::ulid(),
            'payment_id'      => $payment->id,
            'booking_id'      => $booking->id,
            'amount_minor'    => $amount,
            'amount_currency' => 'EGP',
            'reason_code'     => RefundReasonCode::CustomerRequest,
            'reason_notes'    => ['en' => 'exhaust test', 'ar' => 'اختبار استنفاد'],
            'status'          => RefundStatus::Pending,
            'initiated_by'    => $data['customer']->id,
        ]);
        $refund->update(['status' => RefundStatus::Completed]); // simulate already processed
    }

    // Third attempt should be blocked via InitiateRefundAction over-refund guard
    $dto = new InitiateRefundDto(
        paymentId: $payment->id,
        bookingId: $booking->id,
        reasonCode: RefundReasonCode::CustomerRequest,
        reasonNotes: ['en' => 'should fail', 'ar' => 'يجب أن يفشل'],
        requestedAmountMinor: null,
        initiatedBy: $data['customer']->id,
    );

    expect(fn () => app(\App\Modules\Payments\Application\Actions\InitiateRefundAction::class)->execute($dto))
        ->toThrow(OverRefundAttemptedException::class);

    // Ledger group count must not increase
    expect(DB::table('ledger_transaction_groups')->where('kind', 'refund')->count())->toBe(0);
})->group('us4');
