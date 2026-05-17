<?php

declare(strict_types=1);

use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Domain\Exceptions\OverRefundAttemptedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeCapturedPaymentForOverRefundTest(int $amountMinor = 100_000): Payment
{
    $data = makeConfirmedBookingWithItem();

    return Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $data['booking']->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-REFUND-' . Str::ulid(),
        'amount_minor'    => $amountMinor,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Captured,
    ]);
}

it('rejects a refund request that would exceed the captured total', function (): void {
    $payment = makeCapturedPaymentForOverRefundTest(100_000); // 1000 EGP captured
    $dto = new InitiateRefundDto(
        paymentId: $payment->id,
        bookingId: $payment->booking_id,
        reasonCode: RefundReasonCode::CustomerRequest,
        reasonNotes: ['en' => 'Test over-refund', 'ar' => 'اختبار'],
        requestedAmountMinor: null,
        initiatedBy: 1,
    );

    $action = app(InitiateRefundAction::class);

    // First full refund — should succeed
    $action->execute($dto);

    // Payment is now fully refunded; a second full refund must fail
    expect(fn () => $action->execute($dto))
        ->toThrow(OverRefundAttemptedException::class);

    // No duplicate ledger groups
    expect(DB::table('ledger_transaction_groups')->where('kind', 'refund')->count())->toBeLessThanOrEqual(1);
})->group('us4');

it('does not create a ledger entry when an over-refund is attempted', function (): void {
    $payment = makeCapturedPaymentForOverRefundTest(50_000); // 500 EGP captured
    $dto = new InitiateRefundDto(
        paymentId: $payment->id,
        bookingId: $payment->booking_id,
        reasonCode: RefundReasonCode::CustomerRequest,
        reasonNotes: ['en' => 'Over-refund test', 'ar' => 'اختبار'],
        requestedAmountMinor: null,
        initiatedBy: 1,
    );

    $action = app(InitiateRefundAction::class);

    // First refund succeeds
    $action->execute($dto);

    $groupsBefore = DB::table('ledger_transaction_groups')->count();

    // Second should throw without creating any rows
    try {
        $action->execute($dto);
    } catch (OverRefundAttemptedException) {
    }

    expect(DB::table('ledger_transaction_groups')->count())->toBe($groupsBefore);
})->group('us4');
